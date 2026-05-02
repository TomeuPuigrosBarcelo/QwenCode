<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStripeConfig extends Model
{
    use HasFactory;

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

    protected $hidden = [
        'pk_encrypted',
        'sk_encrypted',
        'whsec_encrypted',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Obtener clave pública desencriptada
     */
    public function getPublicKeyAttribute(): ?string
    {
        if (!$this->pk_encrypted) {
            return null;
        }
        return decrypt($this->pk_encrypted);
    }

    /**
     * Obtener clave secreta desencriptada
     */
    public function getSecretKeyAttribute(): ?string
    {
        if (!$this->sk_encrypted) {
            return null;
        }
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
     * Verificar si la configuración está completa y verificada
     */
    public function isConfigured(): bool
    {
        return !empty($this->pk_encrypted) && 
               !empty($this->sk_encrypted) &&
               ($this->last_verified_at !== null && $this->last_verified_at > now()->subDays(30));
    }
}
