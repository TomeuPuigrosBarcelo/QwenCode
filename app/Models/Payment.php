<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'provider',
        'provider_intent_id',
        'amount',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scope para pagos exitosos
     */
    public function scopeSucceeded($query)
    {
        return $query->where('status', 'succeeded');
    }

    /**
     * Scope para pagos pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Verificar si el pago fue con Stripe
     */
    public function isStripe(): bool
    {
        return $this->provider === 'stripe';
    }

    /**
     * Verificar si el pago es reembolsable
     */
    public function isRefundable(): bool
    {
        return $this->isStripe() && $this->status === 'succeeded';
    }
}
