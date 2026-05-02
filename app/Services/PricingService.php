<?php

namespace App\Services;

use App\Models\Property;
use App\Models\SeasonalRate;
use App\Models\BookingRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PricingService
{
    /**
     * Calcular precio total para un rango de fechas
     * Considera tarifas por temporada y reglas de estancia mínima
     */
    public static function calculateTotalPrice(
        int $propertyId,
        Carbon $checkIn,
        Carbon $checkOut,
        int $numGuests = 2
    ): array {
        $nights = $checkIn->diffInDays($checkOut);
        
        if ($nights <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid date range',
                'total' => 0,
                'breakdown' => []
            ];
        }

        $property = Property::find($propertyId);
        
        if (!$property) {
            return [
                'success' => false,
                'error' => 'Property not found',
                'total' => 0,
                'breakdown' => []
            ];
        }

        $breakdown = [];
        $totalPrice = 0;
        $currentDate = clone $checkIn;

        // Iterar día por día para aplicar la tarifa correcta
        while ($currentDate < $checkOut) {
            $rate = self::getRateForDate($propertyId, $currentDate);
            $pricePerNight = $rate ?? $property->base_price ?? 0;
            
            $breakdown[] = [
                'date' => $currentDate->toDateString(),
                'price' => $pricePerNight,
                'rate_type' => $rate ? 'seasonal' : 'default'
            ];

            $totalPrice += $pricePerNight;
            $currentDate->addDay();
        }

        // Aplicar reglas de estancia mínima si es necesario
        $minStay = self::getMinStayForRange($propertyId, $checkIn, $checkOut);
        
        if ($nights < $minStay) {
            return [
                'success' => false,
                'error' => "Minimum stay of {$minStay} nights required",
                'min_stay' => $minStay,
                'total' => 0,
                'breakdown' => []
            ];
        }

        // Aquí se podrían añadir descuentos por estancias largas, extras, etc.
        
        return [
            'success' => true,
            'total' => $totalPrice,
            'nights' => $nights,
            'min_stay_required' => $minStay,
            'breakdown' => $breakdown,
            'currency' => 'EUR' // Configurable por tenant
        ];
    }

    /**
     * Obtener tarifa para una fecha específica
     */
    public static function getRateForDate(int $propertyId, Carbon $date): ?float
    {
        $rate = SeasonalRate::where('property_id', $propertyId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        return $rate ? $rate->price_per_night : null;
    }

    /**
     * Obtener estancia mínima requerida para un rango de fechas
     */
    public static function getMinStayForRange(
        int $propertyId,
        Carbon $checkIn,
        Carbon $checkOut
    ): int {
        $property = Property::find($propertyId);
        $defaultMinStay = $property->min_stay_default ?? 1;

        // Buscar reglas de estancia mínima que se solapen con el rango
        $rule = SeasonalRate::where('property_id', $propertyId)
            ->whereNotNull('min_stay_override')
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->where(function ($q) use ($checkIn) {
                    $q->whereDate('start_date', '<=', $checkIn)
                      ->whereDate('end_date', '>=', $checkIn);
                })
                ->orWhere(function ($q) use ($checkOut) {
                    $q->whereDate('start_date', '<=', $checkOut)
                      ->whereDate('end_date', '>=', $checkOut);
                });
            })
            ->orderByDesc('min_stay_override')
            ->first();

        return $rule ? max($defaultMinStay, $rule->min_stay_override) : $defaultMinStay;
    }

    /**
     * Verificar disponibilidad para un rango de fechas (Anti-Overbooking)
     */
    public static function isAvailable(
        int $propertyId,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeBookingId = null
    ): bool {
        // Usar transacción con lock para evitar race conditions
        return DB::transaction(function () use ($propertyId, $checkIn, $checkOut, $excludeBookingId) {
            // Verificar reglas de bloqueo (BookingRules)
            $blocked = BookingRule::where('property_id', $propertyId)
                ->where(function ($query) use ($checkIn, $checkOut) {
                    // Solapamiento: inicio < check_out Y fin > check_in
                    $query->whereDate('start_date', '<', $checkOut)
                          ->whereDate('end_date', '>', $checkIn);
                })
                ->when($excludeBookingId, function ($query) use ($excludeBookingId) {
                    // Excluir la reserva actual si estamos actualizando
                    return $query->where('id', '!=', $excludeBookingId);
                })
                ->exists();

            if ($blocked) {
                return false;
            }

            // Verificar reservas existentes
            $booked = \App\Models\Booking::where('property_id', $propertyId)
                ->whereIn('status', ['confirmed', 'pending', 'checked_in'])
                ->where(function ($query) use ($checkIn, $checkOut, $excludeBookingId) {
                    $query->whereDate('check_in', '<', $checkOut)
                          ->whereDate('check_out', '>', $checkIn);
                    
                    if ($excludeBookingId) {
                        $query->where('id', '!=', $excludeBookingId);
                    }
                })
                ->lockForUpdate() // Bloqueo pesimista para evitar overbooking
                ->exists();

            return !$booked;
        });
    }

    /**
     * Obtener calendario de disponibilidad y precios para un mes
     */
    public static function getCalendarMonth(
        int $propertyId,
        int $year,
        int $month
    ): array {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = clone $startDate;
        $endDate->endOfMonth();

        $days = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $rate = self::getRateForDate($propertyId, $currentDate);
            $isBlocked = !self::isAvailable($propertyId, $currentDate, (clone $currentDate)->addDay());
            
            $days[] = [
                'date' => $currentDate->toDateString(),
                'price' => $rate,
                'available' => !$isBlocked,
                'day_of_week' => $currentDate->dayOfWeek
            ];

            $currentDate->addDay();
        }

        return $days;
    }
}
