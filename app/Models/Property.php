<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Property extends Model
{
    use HasFactory;

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
        'policies_config' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'area_house_m2' => 'decimal:2',
        'area_land_m2' => 'decimal:2',
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
     * Relación polimórfica para traducciones
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'entity')
            ->select(['id', 'entity_type', 'entity_id', 'field_key', 'locale', 'value', 'is_machine_translated'])
            ->where('entity_type', 'property')
            ->where('entity_id', $this->id);
    }

    /**
     * Obtener nombre traducido
     */
    public function getTranslatedName(string $locale = 'es'): ?string
    {
        return $this->translations()
            ->where('field_key', 'name')
            ->where('locale', $locale)
            ->value('value');
    }

    /**
     * Obtener descripción traducida
     */
    public function getTranslatedDescription(string $locale = 'es'): ?string
    {
        return $this->translations()
            ->where('field_key', 'description')
            ->where('locale', $locale)
            ->value('value');
    }

    /**
     * Scope para propiedades activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
