<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

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
        'guest_details' => 'array',
        'booked_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
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
     * Calcular número de noches
     */
    public function getNightsAttribute(): int
    {
        return $this->check_in->diffInDays($this->check_out);
    }

    /**
     * Verificar si la reserva está confirmada
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Verificar si la reserva está pendiente de pago
     */
    public function isPendingPayment(): bool
    {
        return $this->status === 'pending' && $this->payment_status === 'unpaid';
    }

    /**
     * Scope para reservas activas (no canceladas)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled']);
    }

    /**
     * Scope para reservas en fechas específicas
     */
    public function scopeOverlappingDates($query, $checkIn, $checkOut)
    {
        return $query->where('check_in', '<', $checkOut)
                     ->where('check_out', '>', $checkIn);
    }
}
