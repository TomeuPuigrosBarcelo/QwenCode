<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'reference_code',
        'address',
        'google_maps_place_id',
        'latitude',
        'longitude',
        'area_house_m2',
        'area_land_m2',
        'min_stay_default',
        'check_in_time',
        'check_out_time',
        'policies_config',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'area_house_m2' => 'decimal:2',
        'area_land_m2' => 'decimal:2',
        'policies_config' => 'array',
        'is_active' => 'boolean',
        'check_in_time' => 'datetime:H:i',
        'check_out_time' => 'datetime:H:i',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function seasonalRates(): HasMany
    {
        return $this->hasMany(SeasonalRate::class);
    }

    public function bookingRules(): HasMany
    {
        return $this->hasMany(BookingRule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function icalSyncs(): HasMany
    {
        return $this->hasMany(ICalSync::class);
    }

    /**
     * Scope para propiedades activas de un tenant
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para propiedades de un tenant específico
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Obtener traducción de un campo específico
     */
    public function getTranslation(string $fieldKey, string $locale = null): ?string
    {
        $locale = $locale ?? $this->tenant->default_locale;
        
        // Primero buscar traducción específica del tenant
        $translation = Translation::where('entity_type', 'property')
            ->where('entity_id', $this->id)
            ->where('field_key', $fieldKey)
            ->where('locale', $locale)
            ->first();

        if ($translation) {
            return $translation->value;
        }

        // Si no existe, intentar buscar una global
        $originalValue = $this->$fieldKey;
        if ($originalValue) {
            $contentHash = md5($originalValue);
            
            $globalTranslation = Translation::whereNull('tenant_id')
                ->where('entity_type', 'property')
                ->where('field_key', $fieldKey)
                ->where('locale', $locale)
                ->where('content_hash', $contentHash)
                ->where('is_global', true)
                ->first();

            if ($globalTranslation) {
                return $globalTranslation->value;
            }
        }

        // Fallback al valor original si es el campo base
        if (in_array($fieldKey, ['description'])) {
            return $this->$fieldKey;
        }

        return null;
    }

    /**
     * Generar código de referencia único
     */
    public static function generateReferenceCode(int $tenantId): string
    {
        $prefix = strtoupper(substr(md5(time() . $tenantId), 0, 3));
        $random = strtoupper(substr(md5(random_bytes(16)), 0, 5));
        return "PROP-{$prefix}-{$random}";
    }
}
