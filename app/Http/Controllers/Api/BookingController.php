<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\BookingRule;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Listar reservas del tenant actual
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $query = Booking::where('tenant_id', $tenantId)
            ->with(['property.translations', 'payments']);

        // Filtros opcionales
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('check_in_from')) {
            $query->where('check_in', '>=', $request->check_in_from);
        }

        if ($request->has('check_in_to')) {
            $query->where('check_in', '<=', $request->check_in_to);
        }

        $bookings = $query->orderBy('check_in', 'desc')->get();

        return response()->json($bookings);
    }

    /**
     * Obtener detalle de una reserva
     */
    public function show($id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $booking = Booking::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with(['property.translations', 'payments', 'user'])
            ->firstOrFail();

        return response()->json($booking);
    }

    /**
     * Crear nueva reserva (desde panel o pública)
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'property_id' => 'required|exists:properties,id',
                'check_in' => 'required|date|after_or_equal:today',
                'check_out' => 'required|date|after:check_in',
                'num_guests' => 'required|integer|min:1',
                'guest_email' => 'required|email',
                'guest_phone' => 'required|string',
                'guest_details' => 'nullable|array',
                'payment_method' => 'required|in:stripe,bizum_manual,bank_transfer,cash',
                'deposit_amount' => 'nullable|numeric|min:0',
            ]);

            // Verificar propiedad pertenece al tenant
            $property = Property::where('tenant_id', $tenantId)
                ->where('id', $validated['property_id'])
                ->firstOrFail();

            // ANTI-OVERBOOKING: Verificar disponibilidad con LOCK
            $isAvailable = $this->checkAvailability(
                $property->id, 
                $validated['check_in'], 
                $validated['check_out']
            );

            if (!$isAvailable) {
                return response()->json([
                    'error' => 'Las fechas seleccionadas no están disponibles',
                    'code' => 'OVERBOOKING'
                ], 409);
            }

            // Calcular precio total
            $pricing = $this->calculatePricing(
                $property->id,
                $validated['check_in'],
                $validated['check_out']
            );

            $totalAmount = $pricing['total'];
            $paidAmount = 0;
            $paymentStatus = 'unpaid';

            // Si es pago inmediato con Stripe, se manejará después del Payment Intent
            if ($validated['payment_method'] === 'cash') {
                $paymentStatus = 'pending';
            }

            // Crear reserva
            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'property_id' => $property->id,
                'user_id' => Auth::id(), // O null si es guest sin cuenta
                'guest_email' => $validated['guest_email'],
                'guest_phone' => $validated['guest_phone'],
                'check_in' => $validated['check_in'],
                'check_out' => $validated['check_out'],
                'num_guests' => $validated['num_guests'],
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'deposit_amount' => $validated['deposit_amount'] ?? 0,
                'currency' => 'EUR',
                'status' => 'pending', // pending, confirmed, cancelled, completed
                'payment_status' => $paymentStatus,
                'guest_details' => $validated['guest_details'] ?? [],
                'booked_at' => now(),
            ]);

            // Crear regla de bloqueo temporal hasta confirmación de pago
            BookingRule::create([
                'tenant_id' => $tenantId,
                'property_id' => $property->id,
                'start_date' => $validated['check_in'],
                'end_date' => $validated['check_out'],
                'type' => 'reserved_pending',
                'source' => 'web_direct',
                'external_id' => null,
            ]);

            DB::commit();

            // Aquí se dispararía el Job para enviar email de confirmación/pago

            return response()->json([
                'message' => 'Reserva creada exitosamente',
                'booking' => $booking->load(['property.translations']),
                'pricing_breakdown' => $pricing
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear reserva: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Confirmar reserva (tras pago exitoso)
     */
    public function confirm($id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $booking = Booking::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();

        if ($booking->status === 'confirmed') {
            return response()->json(['message' => 'La reserva ya está confirmada']);
        }

        DB::beginTransaction();
        try {
            // Actualizar estado
            $booking->status = 'confirmed';
            
            // Actualizar regla de bloqueo a confirmed
            BookingRule::where('tenant_id', $tenantId)
                ->where('property_id', $booking->property_id)
                ->where('start_date', $booking->check_in)
                ->where('end_date', $booking->check_out)
                ->where('type', 'reserved_pending')
                ->update(['type' => 'reserved_confirmed']);

            $booking->save();

            DB::commit();

            // Disparar Job de email de confirmación y sincronización Google Calendar

            return response()->json([
                'message' => 'Reserva confirmada exitosamente',
                'booking' => $booking
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al confirmar reserva: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cancelar reserva
     */
    public function cancel($id, Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $booking = Booking::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'La reserva ya está cancelada']);
        }

        DB::beginTransaction();
        try {
            // Calcular reembolso según política de cancelación
            $refundAmount = $this->calculateRefund($booking);

            $booking->status = 'cancelled';
            $booking->save();

            // Eliminar o actualizar regla de bloqueo
            BookingRule::where('tenant_id', $tenantId)
                ->where('property_id', $booking->property_id)
                ->where('start_date', $booking->check_in)
                ->where('end_date', $booking->check_out)
                ->delete();

            // Si hay pago y se debe reembolsar
            if ($refundAmount > 0 && $booking->payment_status !== 'unpaid') {
                // Aquí se llamaría al servicio de Stripe para procesar reembolso
                // StripeService::refund($booking, $refundAmount);
            }

            DB::commit();

            return response()->json([
                'message' => 'Reserva cancelada exitosamente',
                'refund_amount' => $refundAmount,
                'booking' => $booking
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al cancelar reserva: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verificar disponibilidad de fechas (ANTI-OVERBOOKING)
     */
    private function checkAvailability(int $propertyId, string $checkIn, string $checkOut): bool
    {
        // Bloqueo optimista con lockForUpdate
        $conflicts = BookingRule::where('property_id', $propertyId)
            ->where(function($query) use ($checkIn, $checkOut) {
                $query->where(function($q) use ($checkIn, $checkOut) {
                    // Solapamiento: inicio antes del fin Y fin después del inicio
                    $q->where('start_date', '<', $checkOut)
                      ->where('end_date', '>', $checkIn);
                });
            })
            ->whereIn('type', ['blocked', 'maintenance', 'reserved_confirmed', 'reserved_pending', 'reserved_external'])
            ->lockForUpdate()
            ->exists();

        return !$conflicts;
    }

    /**
     * Calcular precio basado en tarifas por temporada
     */
    private function calculatePricing(int $propertyId, string $checkIn, string $checkOut): array
    {
        $start = Carbon::parse($checkIn);
        $end = Carbon::parse($checkOut);
        $nights = $start->diffInDays($end);

        $total = 0;
        $breakdown = [];

        // Lógica simplificada: buscar tarifa que cubra cada noche
        // En producción, esto iteraría día por día aplicando la tarifa correspondiente
        
        $currentDate = clone $start;
        while ($currentDate < $end) {
            $rate = \App\Models\SeasonalRate::where('property_id', $propertyId)
                ->whereDate('start_date', '<=', $currentDate)
                ->whereDate('end_date', '>=', $currentDate)
                ->first();

            $pricePerNight = $rate ? $rate->price_per_night : $this->getDefaultPrice($propertyId);
            
            $total += $pricePerNight;
            
            $breakdown[] = [
                'date' => $currentDate->format('Y-m-d'),
                'price' => $pricePerNight,
                'rate_id' => $rate?->id ?? null
            ];

            $currentDate->addDay();
        }

        return [
            'nights' => $nights,
            'total' => $total,
            'breakdown' => $breakdown,
            'currency' => 'EUR'
        ];
    }

    private function getDefaultPrice(int $propertyId): decimal
    {
        // Precio base por defecto si no hay tarifa específica
        return 100.00;
    }

    /**
     * Calcular reembolso según política y fecha de cancelación
     */
    private function calculateRefund(Booking $booking): decimal
    {
        $policies = $booking->property->policies_config ?? [];
        $cancellationPolicy = $policies['cancellation'] ?? 'moderate'; // flexible, moderate, strict

        $now = now();
        $checkIn = Carbon::parse($booking->check_in);
        $daysUntilCheckIn = $now->diffInDays($checkIn, false);

        $refundPercentage = 0;

        switch ($cancellationPolicy) {
            case 'flexible':
                if ($daysUntilCheckIn >= 1) {
                    $refundPercentage = 100;
                }
                break;
            case 'moderate':
                if ($daysUntilCheckIn >= 5) {
                    $refundPercentage = 100;
                } elseif ($daysUntilCheckIn >= 1) {
                    $refundPercentage = 50;
                }
                break;
            case 'strict':
                if ($daysUntilCheckIn >= 7) {
                    $refundPercentage = 50;
                }
                break;
        }

        return ($booking->paid_amount * $refundPercentage) / 100;
    }
}
