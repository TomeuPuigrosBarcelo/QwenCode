<?php

namespace App\Services\Pricing;

use App\Models\Property;
use App\Models\SeasonalRate;
use App\Models\BookingRule;
use DateTime;

/**
 * Servicio para cálculo de precios dinámicos por temporada
 */
class PricingService
{
    /**
     * Calcular precio total para un rango de fechas
     * 
     * @param Property $property Propiedad
     * @param DateTime $checkIn Fecha de entrada
     * @param DateTime $checkOut Fecha de salida
     * @return array ['total' => float, 'nights' => int, 'breakdown' => array]
     */
    public function calculateTotalPrice(Property $property, DateTime $checkIn, DateTime $checkOut): array
    {
        $nights = $checkIn->diffInDays($checkOut);
        
        if ($nights <= 0) {
            return ['total' => 0, 'nights' => 0, 'breakdown' => []];
        }

        // Verificar mínimo de noches
        $minNights = $this->getMinNightsForDates($property, $checkIn, $checkOut);
        if ($nights < $minNights) {
            throw new \Exception("Mínimo de {$minNights} noches requerido para estas fechas");
        }

        $breakdown = [];
        $total = 0;
        $current = clone $checkIn;

        while ($current < $checkOut) {
            $price = $this->getPriceForDate($property, $current);
            $dateStr = $current->format('Y-m-d');
            
            $breakdown[] = [
                'date' => $dateStr,
                'price' => $price,
            ];

            $total += $price;
            $current->modify('+1 day');
        }

        return [
            'total' => round($total, 2),
            'nights' => $nights,
            'breakdown' => $breakdown,
            'average_per_night' => round($total / $nights, 2),
        ];
    }

    /**
     * Obtener precio para una fecha específica
     */
    public function getPriceForDate(Property $property, DateTime $date): float
    {
        // Buscar tarifa seasonal específica
        $rate = SeasonalRate::where('property_id', $property->id)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($rate) {
            return (float)$rate->price_per_night;
        }

        // Si no hay tarifa específica, retornar un precio base (podría venir de la propiedad)
        // En este caso usamos un default configurable
        return config('pricing.default_price', 100.00);
    }

    /**
     * Obtener mínimo de noches para un rango de fechas
     */
    public function getMinNightsForDates(Property $property, DateTime $checkIn, DateTime $checkOut): int
    {
        // Prioridad 1: Verificar si hay override en seasonal rates para alguna fecha del rango
        $current = clone $checkIn;
        $maxMinStay = 0;

        while ($current < $checkOut) {
            $rate = SeasonalRate::where('property_id', $property->id)
                ->whereDate('start_date', '<=', $current)
                ->whereDate('end_date', '>=', $current)
                ->whereNotNull('min_stay_override')
                ->first();

            if ($rate && $rate->min_stay_override > $maxMinStay) {
                $maxMinStay = $rate->min_stay_override;
            }

            $current->modify('+1 day');
        }

        // Si hay override, usar ese
        if ($maxMinStay > 0) {
            return $maxMinStay;
        }

        // Prioridad 2: Usar el default de la propiedad
        return $property->min_stay_default ?? 1;
    }

    /**
     * Verificar disponibilidad para un rango de fechas
     * Retorna true si está disponible, false si hay conflicto
     */
    public function isAvailable(Property $property, DateTime $checkIn, DateTime $checkOut): bool
    {
        // 1. Verificar si hay fechas bloqueadas manualmente
        $current = clone $checkIn;
        while ($current < $checkOut) {
            if (BookingRule::isBlocked($property->id, $current)) {
                return false;
            }
            $current->modify('+1 day');
        }

        // 2. Verificar solapamiento con reservas existentes (Anti-Overbooking)
        if (\App\Models\Booking::hasOverlap($property->id, $checkIn, $checkOut)) {
            return false;
        }

        return true;
    }

    /**
     * Obtener fechas disponibles en un rango mensual
     * Útil para mostrar calendario de disponibilidad
     */
    public function getAvailableDates(Property $property, DateTime $startDate, DateTime $endDate): array
    {
        $available = [];
        $blocked = [];
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            
            if ($this->isAvailable($property, $current, (clone $current)->modify('+1 day'))) {
                $available[] = $dateStr;
            } else {
                $blocked[] = $dateStr;
            }

            $current->modify('+1 day');
        }

        return [
            'available' => $available,
            'blocked' => $blocked,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
    }
}
