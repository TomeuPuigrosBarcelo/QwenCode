<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'field_key',
        'locale',
        'value',
        'is_machine_translated',
        'is_global',
        'content_hash',
    ];

    protected $casts = [
        'is_machine_translated' => 'boolean',
        'is_global' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para traducciones de un tenant específico o globales
     */
    public function scopeForTenantOrGlobal($query, $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)
              ->orWhereNull('tenant_id'); // Globales
        });
    }

    /**
     * Scope para traducciones globales compartidas
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_global', true)->whereNull('tenant_id');
    }

    /**
     * Scope para buscar por hash de contenido (optimización pool global)
     */
    public function scopeByContentHash($query, string $contentHash)
    {
        return $query->where('content_hash', $contentHash);
    }

    /**
     * Crear o actualizar traducción con soporte para pool global
     */
    public static function createOrUpdateTranslation(
        string $entityType,
        int $entityId,
        string $fieldKey,
        string $locale,
        string $value,
        ?int $tenantId = null,
        bool $isMachineTranslated = false,
        bool $tryGlobal = false
    ): self {
        $contentHash = md5($value);

        // Si se solicita intentar usar pool global, buscar primero
        if ($tryGlobal) {
            $globalTranslation = static::whereNull('tenant_id')
                ->where('entity_type', $entityType)
                ->where('field_key', $fieldKey)
                ->where('locale', $locale)
                ->where('content_hash', $contentHash)
                ->where('is_global', true)
                ->first();

            if ($globalTranslation) {
                return $globalTranslation;
            }
        }

        // Buscar existente del tenant
        $translation = static::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('field_key', $fieldKey)
            ->where('locale', $locale)
            ->first();

        if ($translation) {
            $translation->update([
                'value' => $value,
                'is_machine_translated' => $isMachineTranslated,
                'content_hash' => $contentHash,
            ]);
            return $translation;
        }

        // Crear nueva
        return static::create([
            'tenant_id' => $tenantId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field_key' => $fieldKey,
            'locale' => $locale,
            'value' => $value,
            'is_machine_translated' => $isMachineTranslated,
            'is_global' => false,
            'content_hash' => $contentHash,
        ]);
    }

    /**
     * Marcar traducción como global (compartible)
     */
    public function markAsGlobal(): void
    {
        $this->update([
            'is_global' => true,
            'tenant_id' => null,
        ]);
    }
}
