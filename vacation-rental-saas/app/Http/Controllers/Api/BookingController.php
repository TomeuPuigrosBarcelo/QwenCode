<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRule;
use App\Services\Pricing\PricingService;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UpdateBookingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Listar reservas del tenant con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()
            ->with(['property', 'payments', 'translations'])
            ->where('tenant_id', $request->tenant->id);

        // Filtros
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

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $bookings = $query->orderBy('check_in', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'bookings' => $bookings,
        ]);
    }

    /**
     * Obtener detalle de una reserva
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $booking = Booking::where('tenant_id', $request->tenant->id)
            ->with(['property', 'payments', 'translations'])
            ->findOrFail($id);

        return response()->json([
            'booking' => $booking,
        ]);
    }

    /**
     * Crear nueva reserva (desde panel o pública)
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            $property = \App\Models\Property::where('tenant_id', $request->tenant->id)
                ->findOrFail($validated['property_id']);

            // VERIFICAR DISPONIBILIDAD (Anti-Overbooking)
            $isAvailable = $this->checkAvailability(
                $property->id,
                $validated['check_in'],
                $validated['check_out'],
                $request->tenant->id
            );

            if (!$isAvailable) {
                return response()->json([
                    'message' => 'Las fechas seleccionadas no están disponibles',
                    'error_code' => 'DATES_NOT_AVAILABLE',
                ], 409);
            }

            // Calcular precio total
            $pricing = $this->pricingService->calculateTotalPrice(
                $property->id,
                $validated['check_in'],
                $validated['check_out'],
                $validated['num_guests'] ?? 2
            );

            // Calcular fianza si aplica
            $depositAmount = $property->policies_config['deposit_amount'] ?? 0;

            // Crear reserva
            $booking = Booking::create([
                'tenant_id' => $request->tenant->id,
                'property_id' => $property->id,
                'user_id' => $validated['user_id'] ?? null,
                'guest_email' => $validated['guest_email'],
                'guest_phone' => $validated['guest_phone'],
                'check_in' => $validated['check_in'],
                'check_out' => $validated['check_out'],
                'num_guests' => $validated['num_guests'] ?? 2,
                'total_amount' => $pricing['total'],
                'paid_amount' => 0,
                'deposit_amount' => $depositAmount,
                'currency' => $validated['currency'] ?? 'EUR',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'guest_details' => $validated['guest_details'] ?? [],
                'booked_at' => now(),
            ]);

            // Guardar traducciones de notas del huésped si existen
            if (!empty($validated['guest_notes'])) {
                \App\Models\Translation::create([
                    'tenant_id' => $request->tenant->id,
                    'entity_type' => 'booking',
                    'entity_id' => $booking->id,
                    'field_key' => 'guest_notes',
                    'locale' => $validated['guest_notes_locale'] ?? $request->tenant->default_locale,
                    'value' => $validated['guest_notes'],
                    'is_machine_translated' => false,
                ]);
            }

            // Registrar regla de bloqueo temporal mientras se procesa el pago
            BookingRule::create([
                'tenant_id' => $request->tenant->id,
                'property_id' => $property->id,
                'start_date' => $validated['check_in'],
                'end_date' => $validated['check_out'],
                'type' => 'reserved_pending_payment',
                'source' => 'direct_booking',
                'external_id' => "booking_{$booking->id}",
            ]);

            DB::commit();

            $booking->load(['property', 'translations']);

            // TODO: Disparar Job para enviar email de confirmación/pago pendiente

            return response()->json([
                'message' => 'Reserva creada exitosamente',
                'booking' => $booking,
                'pricing_breakdown' => $pricing,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creando reserva', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $request->tenant->id,
            ]);

            return response()->json([
                'message' => 'Error al crear la reserva',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Actualizar estado de reserva (confirmar, cancelar, completar)
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $booking = Booking::where('tenant_id', $request->tenant->id)->findOrFail($id);
            $validated = $request->validate([
                'status' => ['required', 'in:pending,confirmed,cancelled,completed'],
                'cancel_reason' => ['nullable', 'string', 'max:500'],
            ]);

            $oldStatus = $booking->status;
            $newStatus = $validated['status'];

            // Validaciones de transición de estado
            if ($oldStatus === 'completed' && $newStatus !== 'completed') {
                return response()->json([
                    'message' => 'No se puede modificar una reserva completada',
                ], 400);
            }

            // Si se cancela, verificar política de reembolso
            if ($newStatus === 'cancelled') {
                $refundAmount = $this->calculateRefundAmount($booking);
                
                // Actualizar reserva
                $booking->update([
                    'status' => $newStatus,
                ]);

                // Guardar motivo de cancelación
                if (!empty($validated['cancel_reason'])) {
                    \App\Models\Translation::create([
                        'tenant_id' => $request->tenant->id,
                        'entity_type' => 'booking',
                        'entity_id' => $booking->id,
                        'field_key' => 'cancel_reason',
                        'locale' => $request->tenant->default_locale,
                        'value' => $validated['cancel_reason'],
                        'is_machine_translated' => false,
                    ]);
                }

                // Eliminar regla de bloqueo
                BookingRule::where('external_id', "booking_{$booking->id}")
                    ->delete();

                // TODO: Procesar reembolso si hay pagos y la política lo permite
                // TODO: Enviar email de cancelación

                DB::commit();

                return response()->json([
                    'message' => 'Reserva cancelada exitosamente',
                    'booking' => $booking->fresh(),
                    'refund_info' => [
                        'eligible_amount' => $refundAmount,
                        'policy_applied' => $booking->property->policies_config['cancellation_policy'] ?? 'moderate',
                    ],
                ]);
            }

            // Si se confirma
            if ($newStatus === 'confirmed') {
                $booking->update([
                    'status' => $newStatus,
                ]);

                // Actualizar regla de bloqueo a confirmada
                BookingRule::where('external_id', "booking_{$booking->id}")
                    ->update([
                        'type' => 'reserved_confirmed',
                    ]);

                // TODO: Enviar email de confirmación

                DB::commit();

                return response()->json([
                    'message' => 'Reserva confirmada exitosamente',
                    'booking' => $booking->fresh(),
                ]);
            }

            // Otros estados
            $booking->update([
                'status' => $newStatus,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Estado de reserva actualizado',
                'booking' => $booking->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error actualizando estado de reserva', [
                'error' => $e->getMessage(),
                'tenant_id' => $request->tenant->id,
            ]);

            return response()->json([
                'message' => 'Error al actualizar el estado',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Verificar disponibilidad de fechas (Anti-Overbooking)
     */
    private function checkAvailability(int $propertyId, string $checkIn, string $checkOut, int $tenantId): bool
    {
        // Verificar reglas de bloqueo
        $hasBlockingRule = BookingRule::where('property_id', $propertyId)
            ->where('tenant_id', $tenantId)
            ->where(function($query) use ($checkIn, $checkOut) {
                $query->where(function($q) use ($checkIn, $checkOut) {
                    // Regla que comienza antes del check-out y termina después del check-in
                    $q->where('start_date', '<', $checkOut)
                      ->where('end_date', '>', $checkIn);
                });
            })
            ->whereIn('type', ['blocked', 'maintenance', 'reserved_confirmed', 'reserved_pending_payment'])
            ->exists();

        if ($hasBlockingRule) {
            return false;
        }

        // Verificar reservas existentes
        $hasExistingBooking = Booking::where('property_id', $propertyId)
            ->where('tenant_id', $tenantId)
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($hasExistingBooking) {
            return false;
        }

        return true;
    }

    /**
     * Calcular monto de reembolso según política
     */
    private function calculateRefundAmount(Booking $booking): float
    {
        $policy = $booking->property->policies_config['cancellation_policy'] ?? 'moderate';
        $totalPaid = $booking->paid_amount;
        $now = now();
        $checkInDate = \Carbon\Carbon::parse($booking->check_in);
        $daysUntilCheckIn = $now->diffInDays($checkInDate, false);

        $refundPercentage = match($policy) {
            'flexible' => $daysUntilCheckIn >= 1 ? 100 : 50,
            'moderate' => $daysUntilCheckIn >= 5 ? 100 : ($daysUntilCheckIn >= 1 ? 50 : 0),
            'strict' => $daysUntilCheckIn >= 7 ? 50 : 0,
            default => 50,
        };

        return ($totalPaid * $refundPercentage) / 100;
    }
}
