<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonalRate extends Model
{
    protected $fillable = [
        'tenant_id',
        'property_id',
        'start_date',
        'end_date',
        'price_per_night',
        'min_stay_override',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price_per_night' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope para tarifas de una propiedad en un rango de fechas
     */
    public function scopeForPropertyAndDateRange($query, int $propertyId, $startDate, $endDate)
    {
        return $query->where('property_id', $propertyId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            });
    }

    /**
     * Obtener precio para una fecha específica
     */
    public static function getPriceForDate(int $propertyId, \DateTime $date, float $defaultPrice): float
    {
        $rate = static::where('property_id', $propertyId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        return $rate ? (float)$rate->price_per_night : $defaultPrice;
    }

    /**
     * Calcular precio total para un rango de fechas
     */
    public static function calculateTotalPrice(
        int $propertyId, 
        \DateTime $checkIn, 
        \DateTime $checkOut, 
        float $defaultPrice
    ): float {
        $total = 0;
        $current = clone $checkIn;

        while ($current < $checkOut) {
            $total += static::getPriceForDate($propertyId, $current, $defaultPrice);
            $current->modify('+1 day');
        }

        return $total;
    }
}
