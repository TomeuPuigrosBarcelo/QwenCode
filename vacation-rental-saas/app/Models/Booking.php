<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    protected $fillable = [
        'tenant_id',
        'property_id',
        'user_id',
        'guest_email',
        'guest_phone',
        'check_in',
        'check_out',
        'num_guests',
        'total_amount',
        'paid_amount',
        'deposit_amount',
        'currency',
        'status',
        'payment_status',
        'guest_details',
        'booked_at',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'guest_details' => 'array',
        'booked_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope para reservas de un tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope para reservas en un rango de fechas específico
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            // Reservas que comienzan dentro del rango
            $q->whereBetween('check_in', [$startDate, $endDate])
              // O que terminan dentro del rango
              ->orWhereBetween('check_out', [$startDate, $endDate])
              // O que envuelven completamente el rango
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('check_in', '<=', $startDate)
                     ->where('check_out', '>=', $endDate);
              });
        });
    }

    /**
     * Scope para reservas confirmadas o pendientes (no canceladas)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed', 'completed']);
    }

    /**
     * Verificar si hay solapamiento con otras reservas (Anti-Overbooking)
     */
    public static function hasOverlap(int $propertyId, \DateTime $checkIn, \DateTime $checkOut, ?int $excludeBookingId = null): bool
    {
        $query = static::where('property_id', $propertyId)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where(function ($q2) use ($checkIn, $checkOut) {
                    $q2->whereDate('check_in', '<=', $checkOut->format('Y-m-d'))
                       ->whereDate('check_out', '>', $checkIn->format('Y-m-d'));
                });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->exists();
    }

    /**
     * Calcular número de noches
     */
    public function getNightsAttribute(): int
    {
        return $this->check_in->diffInDays($this->check_out);
    }

    /**
     * Calcular cantidad pendiente de pago
     */
    public function getPendingAmountAttribute(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }
}
