<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class)
            ->where('entity_type', 'email_template')
            ->where('entity_id', $this->id);
    }

    /**
     * Obtener contenido traducido del template
     */
    public function getContent(string $fieldKey, string $locale = null): ?string
    {
        $locale = $locale ?? $this->tenant->default_locale;
        
        $translation = Translation::where('entity_type', 'email_template')
            ->where('entity_id', $this->id)
            ->where('field_key', $fieldKey)
            ->where('locale', $locale)
            ->first();

        return $translation?->value;
    }

    /**
     * Scope para templates activos de un tenant
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para templates de un tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
