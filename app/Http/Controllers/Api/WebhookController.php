<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeServiceFactory;
use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    /**
     * Manejar webhooks de Stripe
     * Este endpoint debe estar público (sin middleware de tenant)
     * El tenant se identifica desde el metadata del evento
     */
    public function handleStripe(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (!$payload || !$sigHeader) {
            Log::warning('Stripe webhook received without signature or payload');
            return response()->json(['error' => 'Missing signature'], 400);
        }

        // Primero necesitamos encontrar el tenant para verificar la firma
        // Parseamos el payload para obtener el tenant_id del metadata
        $eventData = json_decode($payload, true);
        
        if (!isset($eventData['data']['object']['metadata']['tenant_id'])) {
            // Para eventos de cuenta o sin metadata, loguear y retornar error
            Log::warning('Stripe webhook without tenant_id in metadata', [
                'event_type' => $eventData['type'] ?? 'unknown'
            ]);
            return response()->json(['error' => 'Missing tenant_id'], 400);
        }

        $tenantId = (int) $eventData['data']['object']['metadata']['tenant_id'];

        // Verificar firma del webhook
        $event = StripeServiceFactory::verifyWebhookSignature($payload, $sigHeader, $tenantId);

        if (!$event) {
            Log::error('Stripe webhook signature verification failed', [
                'tenant_id' => $tenantId
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info('Stripe webhook received', [
            'event_type' => $event->type,
            'tenant_id' => $tenantId,
            'event_id' => $event->id
        ]);

        // Procesar según el tipo de evento
        DB::beginTransaction();
        
        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event->data->object, $tenantId);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event->data->object, $tenantId);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event->data->object, $tenantId);
                    break;

                case 'charge.dispute.created':
                    $this->handleDisputeCreated($event->data->object, $tenantId);
                    break;

                default:
                    Log::info('Unhandled Stripe event type', ['type' => $event->type]);
            }

            DB::commit();
            
            return response()->json(['received' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing Stripe webhook', [
                'error' => $e->getMessage(),
                'event_id' => $event->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Manejar pago exitoso
     */
    private function handlePaymentSucceeded($paymentIntent, int $tenantId)
    {
        $bookingId = (int) ($paymentIntent->metadata->booking_id ?? 0);
        
        if (!$bookingId) {
            Log::warning('Payment succeeded without booking_id', [
                'payment_intent_id' => $paymentIntent->id
            ]);
            return;
        }

        $booking = Booking::where('id', $bookingId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$booking) {
            Log::error('Booking not found for payment succeeded', [
                'booking_id' => $bookingId,
                'tenant_id' => $tenantId
            ]);
            return;
        }

        // Actualizar o crear el registro de pago
        $payment = Payment::where('provider_intent_id', $paymentIntent->id)
            ->where('tenant_id', $tenantId)
            ->first();

        $amountReceived = $paymentIntent->amount_received / 100; // Convertir de céntimos

        if ($payment) {
            $payment->update([
                'status' => 'succeeded',
                'amount' => $amountReceived,
                'metadata' => array_merge(
                    $payment->metadata ?? [],
                    ['stripe_event_id' => $paymentIntent->id]
                )
            ]);
        } else {
            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'booking_id' => $bookingId,
                'provider' => 'stripe',
                'provider_intent_id' => $paymentIntent->id,
                'amount' => $amountReceived,
                'currency' => $paymentIntent->currency,
                'status' => 'succeeded',
                'metadata' => ['stripe_event_id' => $paymentIntent->id]
            ]);
        }

        // Actualizar reserva
        $newPaidAmount = $booking->paid_amount + $amountReceived;
        
        $booking->update([
            'paid_amount' => $newPaidAmount,
            'payment_status' => $newPaidAmount >= $booking->total_amount ? 'paid' : 'partial',
            'status' => $newPaidAmount >= $booking->total_amount ? 'confirmed' : $booking->status
        ]);

        Log::info('Payment succeeded processed', [
            'booking_id' => $bookingId,
            'amount' => $amountReceived,
            'tenant_id' => $tenantId
        ]);

        // TODO: Disparar email de confirmación de pago
    }

    /**
     * Manejar pago fallido
     */
    private function handlePaymentFailed($paymentIntent, int $tenantId)
    {
        $bookingId = (int) ($paymentIntent->metadata->booking_id ?? 0);
        
        if (!$bookingId) {
            return;
        }

        $payment = Payment::where('provider_intent_id', $paymentIntent->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'metadata' => array_merge(
                    $payment->metadata ?? [],
                    [
                        'failure_reason' => $paymentIntent->last_payment_error->message ?? null,
                        'failed_at' => now()->toIso8601String()
                    ]
                )
            ]);
        }

        Log::warning('Payment failed', [
            'booking_id' => $bookingId,
            'reason' => $paymentIntent->last_payment_error->message ?? 'Unknown',
            'tenant_id' => $tenantId
        ]);

        // TODO: Notificar al propietario y al cliente
    }

    /**
     * Manejar reembolso
     */
    private function handleChargeRefunded($charge, int $tenantId)
    {
        $paymentIntentId = $charge->payment_intent ?? null;
        
        if (!$paymentIntentId) {
            return;
        }

        $payment = Payment::where('provider_intent_id', $paymentIntentId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($payment) {
            $refundAmount = isset($charge->refunds->data[0]) 
                ? $charge->refunds->data[0]->amount / 100 
                : 0;

            $payment->update([
                'status' => 'refunded',
                'metadata' => array_merge(
                    $payment->metadata ?? [],
                    [
                        'refunded_amount' => $refundAmount,
                        'refund_charge_id' => $charge->id,
                        'refunded_at' => now()->toIso8601String()
                    ]
                )
            ]);

            Log::info('Charge refunded', [
                'payment_id' => $payment->id,
                'amount' => $refundAmount,
                'tenant_id' => $tenantId
            ]);
        }
    }

    /**
     * Manejar disputa (chargeback)
     */
    private function handleDisputeCreated($charge, int $tenantId)
    {
        $paymentIntentId = $charge->payment_intent ?? null;
        
        if (!$paymentIntentId) {
            return;
        }

        $payment = Payment::where('provider_intent_id', $paymentIntentId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($payment) {
            $payment->update([
                'status' => 'disputed',
                'metadata' => array_merge(
                    $payment->metadata ?? [],
                    [
                        'dispute_id' => $charge->dispute->id ?? null,
                        'dispute_reason' => $charge->dispute->reason ?? null,
                        'disputed_at' => now()->toIso8601String()
                    ]
                )
            ]);

            Log::alert('Charge dispute created', [
                'payment_id' => $payment->id,
                'reason' => $charge->dispute->reason ?? 'Unknown',
                'tenant_id' => $tenantId
            ]);

            // TODO: Notificar urgentemente al propietario
        }
    }
}
