<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'subdomain',
        'custom_domain',
        'status',
        'branding',
        'default_locale',
        'subscribed_until',
    ];

    protected $casts = [
        'branding' => 'array',
        'subscribed_until' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function stripeConfig(): HasOne
    {
        return $this->hasOne(TenantStripeConfig::class);
    }

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function systemLogs(): HasMany
    {
        return $this->hasMany(SystemLog::class);
    }

    /**
     * Scope para obtener solo tenants activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Obtener configuración de branding con valores por defecto
     */
    public function getBrandingAttribute($value)
    {
        $default = [
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1E40AF',
            'logo_url' => null,
            'favicon_url' => null,
        ];

        if (!$value) {
            return $default;
        }

        return array_merge($default, is_string($value) ? json_decode($value, true) : $value);
    }
}
