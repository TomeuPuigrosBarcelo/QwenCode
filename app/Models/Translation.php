<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'field_key',
        'locale',
        'value',
        'is_machine_translated',
    ];

    protected $casts = [
        'is_machine_translated' => 'boolean',
    ];

    /**
     * Relación polimórfica inversa (opcional, para conveniencia)
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para obtener traducciones globales (pool compartido)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Scope para obtener traducciones de un tenant específico
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Obtener traducción para una entidad específica
     */
    public static function getTranslation(
        string $entityType,
        int $entityId,
        string $fieldKey,
        string $locale,
        ?int $tenantId = null,
        string $fallbackLocale = 'es'
    ): ?string {
        // 1. Intentar obtener traducción del tenant
        $translation = static::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('field_key', $fieldKey)
            ->where('locale', $locale)
            ->first();

        if ($translation) {
            return $translation->value;
        }

        // 2. Si no existe, intentar con locale fallback
        if ($locale !== $fallbackLocale) {
            $fallbackTranslation = static::where('tenant_id', $tenantId)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('field_key', $fieldKey)
                ->where('locale', $fallbackLocale)
                ->first();

            if ($fallbackTranslation) {
                return $fallbackTranslation->value;
            }
        }

        // 3. Intentar buscar en pool global
        $globalTranslation = static::whereNull('tenant_id')
            ->where('entity_type', 'structural')
            ->where('field_key', 'like', 'content_hash_%')
            ->where('locale', $locale)
            ->get()
            ->first(function ($t) use ($entityType, $entityId, $fieldKey) {
                // Aquí podríamos implementar lógica más compleja si guardamos referencia
                return false; // Simplificado
            });

        return null; // No se encontró traducción
    }

    /**
     * Verificar si existe traducción en pool global para un contenido
     */
    public static function findInGlobalPool(string $content, string $locale): ?string
    {
        $contentHash = md5($content);
        
        return static::whereNull('tenant_id')
            ->where('entity_type', 'structural')
            ->where('field_key', 'content_hash_' . $contentHash)
            ->where('locale', $locale)
            ->value('value');
    }
}
