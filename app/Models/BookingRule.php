<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRule extends Model
{
    use HasFactory;

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
     * Scope para reglas que se solapan con un rango de fechas (ANTI-OVERBOOKING)
     */
    public function scopeOverlappingDates($query, $startDate, $endDate)
    {
        return $query->where('start_date', '<', $endDate)
                     ->where('end_date', '>', $startDate);
    }

    /**
     * Scope para reglas de bloqueo (no disponibles)
     */
    public function scopeBlocking($query)
    {
        return $query->whereIn('type', [
            'blocked',
            'maintenance',
            'reserved_confirmed',
            'reserved_pending',
            'reserved_external'
        ]);
    }

    /**
     * Verificar si esta regla bloquea las fechas proporcionadas
     */
    public function blocksDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): bool
    {
        return $this->start_date < $endDate && $this->end_date > $startDate;
    }
}
