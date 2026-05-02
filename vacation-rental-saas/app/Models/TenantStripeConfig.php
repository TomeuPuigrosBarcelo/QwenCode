<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStripeConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'account_type',
        'pk_encrypted',
        'sk_encrypted',
        'whsec_encrypted',
        'test_mode',
        'last_verified_at',
    ];

    protected $casts = [
        'test_mode' => 'boolean',
        'last_verified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Obtener clave pública desencriptada
     */
    public function getPublicKeyAttribute(): string
    {
        return decrypt($this->pk_encrypted);
    }

    /**
     * Obtener clave secreta desencriptada
     */
    public function getSecretKeyAttribute(): string
    {
        return decrypt($this->sk_encrypted);
    }

    /**
     * Obtener webhook secret desencriptado
     */
    public function getWebhookSecretAttribute(): ?string
    {
        if (!$this->whsec_encrypted) {
            return null;
        }
        return decrypt($this->whsec_encrypted);
    }

    /**
     * Establecer clave pública encriptada
     */
    public function setPublicKeyAttribute(string $value): void
    {
        $this->attributes['pk_encrypted'] = encrypt($value);
    }

    /**
     * Establecer clave secreta encriptada
     */
    public function setSecretKeyAttribute(string $value): void
    {
        $this->attributes['sk_encrypted'] = encrypt($value);
    }

    /**
     * Establecer webhook secret encriptado
     */
    public function setWebhookSecretAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['whsec_encrypted'] = encrypt($value);
        }
    }

    /**
     * Verificar si la configuración está completa y válida
     */
    public function isValid(): bool
    {
        return !empty($this->pk_encrypted) 
            && !empty($this->sk_encrypted)
            && $this->last_verified_at !== null;
    }
}
