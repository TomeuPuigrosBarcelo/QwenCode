<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Booking;
use App\Services\StripeServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Obtener pagos de una reserva
     */
    public function index(Request $request, int $bookingId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $booking = Booking::where('id', $bookingId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $payments = Payment::where('booking_id', $bookingId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'payments' => $payments
        ]);
    }

    /**
     * Obtener detalle de un pago
     */
    public function show(int $paymentId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $payment = Payment::where('id', $paymentId)
            ->whereHas('booking', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'payment' => $payment
        ]);
    }

    /**
     * Reembolsar un pago (solo Stripe)
     */
    public function refund(Request $request, int $paymentId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $payment = Payment::where('id', $paymentId)
            ->where('provider', 'stripe')
            ->whereHas('booking', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->firstOrFail();

        if ($payment->status !== 'succeeded') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden reembolsar pagos completados'
            ], 400);
        }

        $validated = $request->validate([
            'amount' => 'nullable|integer|min:1', // En la moneda original, no céntimos
            'reason' => 'nullable|string|max:500'
        ]);

        $refundAmount = $validated['amount'] ?? $payment->amount;
        
        // Convertir a céntimos para Stripe
        $refundAmountCents = $refundAmount * 100;

        $success = StripeServiceFactory::refundPayment(
            $tenantId,
            $payment->provider_intent_id,
            $refundAmountCents
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el reembolso'
            ], 500);
        }

        // Actualizar estado del pago
        $payment->update([
            'status' => 'refunded',
            'metadata' => array_merge(
                $payment->metadata ?? [],
                [
                    'refunded_amount' => $refundAmount,
                    'refund_reason' => $validated['reason'] ?? null,
                    'refunded_at' => now()->toIso8601String()
                ]
            )
        ]);

        // Actualizar reserva si es reembolso total
        if ($refundAmount >= $payment->amount) {
            $payment->booking->update([
                'payment_status' => 'refunded',
                'status' => 'cancelled'
            ]);
        } else {
            $payment->booking->update([
                'payment_status' => 'refunded_partial'
            ]);
        }

        Log::info("Refund processed", [
            'payment_id' => $paymentId,
            'amount' => $refundAmount,
            'tenant_id' => $tenantId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reembolso procesado correctamente',
            'refunded_amount' => $refundAmount
        ]);
    }

    /**
     * Confirmar pago manual (Bizum, Transferencia)
     */
    public function confirmManual(Request $request, int $paymentId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $payment = Payment::where('id', $paymentId)
            ->whereIn('provider', ['bizum_manual', 'bank_transfer', 'cash'])
            ->whereHas('booking', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->firstOrFail();

        if ($payment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'El pago ya ha sido procesado'
            ], 400);
        }

        $payment->update([
            'status' => 'succeeded'
        ]);

        // Actualizar reserva
        $booking = $payment->booking;
        $newPaidAmount = $booking->paid_amount + $payment->amount;
        
        $booking->update([
            'paid_amount' => $newPaidAmount,
            'payment_status' => $newPaidAmount >= $booking->total_amount ? 'paid' : 'partial',
            'status' => $newPaidAmount >= $booking->total_amount ? 'confirmed' : $booking->status
        ]);

        Log::info("Manual payment confirmed", [
            'payment_id' => $paymentId,
            'booking_id' => $booking->id,
            'tenant_id' => $tenantId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pago confirmado correctamente',
            'payment' => $payment->fresh(),
            'booking' => $booking->fresh()
        ]);
    }

    /**
     * Obtener estadísticas de pagos del tenant
     */
    public function stats(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        $query = Payment::where('tenant_id', $tenantId)
            ->where('status', 'succeeded');

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $totalRevenue = $query->sum('amount');
        $totalTransactions = $query->count();
        
        // Por método de pago
        $byProvider = Payment::where('tenant_id', $tenantId)
            ->where('status', 'succeeded')
            ->when(isset($validated['date_from']), function ($q) use ($validated) {
                $q->whereDate('created_at', '>=', $validated['date_from']);
            })
            ->when(isset($validated['date_to']), function ($q) use ($validated) {
                $q->whereDate('created_at', '<=', $validated['date_to']);
            })
            ->selectRaw('provider, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('provider')
            ->get();

        // Mes actual
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        
        $monthRevenue = Payment::where('tenant_id', $tenantId)
            ->where('status', 'succeeded')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        return response()->json([
            'success' => true,
            'stats' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'current_month_revenue' => $monthRevenue,
                'by_provider' => $byProvider
            ]
        ]);
    }
}
