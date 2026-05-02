<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeasonalRate extends Model
{
    use HasFactory;

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
        'min_stay_override' => 'integer',
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
     * Scope para tarifas que se solapan con un rango de fechas
     */
    public function scopeOverlappingDates($query, $startDate, $endDate)
    {
        return $query->where('start_date', '<=', $endDate)
                     ->where('end_date', '>=', $startDate);
    }

    /**
     * Verificar si una fecha específica está dentro del rango
     */
    public function containsDate(\DateTimeInterface $date): bool
    {
        return $date >= $this->start_date && $date <= $this->end_date;
    }
}
