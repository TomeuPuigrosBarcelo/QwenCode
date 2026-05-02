<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRule extends Model
{
    protected $fillable = [
        'tenant_id',
        'property_id',
        'start_date',
        'end_date',
        'type',
        'source',
        'external_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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
     * Scope para reglas de una propiedad en rango de fechas
     */
    public function scopeForPropertyAndDateRange($query, ?int $propertyId, $startDate, $endDate)
    {
        $query = $query->where(function ($q) use ($propertyId) {
            if ($propertyId) {
                $q->where('property_id', $propertyId)
                  ->orWhereNull('property_id'); // Reglas globales
            } else {
                $q->whereNull('property_id');
            }
        });

        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where('end_date', '>=', $endDate);
              });
        });
    }

    /**
     * Verificar si una fecha está bloqueada
     */
    public static function isBlocked(int $propertyId, \DateTime $date): bool
    {
        return static::where(function ($q) use ($propertyId) {
                $q->where('property_id', $propertyId)
                  ->orWhereNull('property_id');
            })
            ->where('type', 'blocked')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}
