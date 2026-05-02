<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTranslationJob extends Model
{
    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'source_lang',
        'target_lang',
        'status',
        'original_content',
        'translated_content',
        'error_message',
    ];

    protected $casts = [
        'original_content' => 'string',
        'translated_content' => 'string',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para jobs de un tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope para jobs pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para jobs fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Marcar job como completado
     */
    public function markAsCompleted(string $translatedContent): void
    {
        $this->update([
            'status' => 'completed',
            'translated_content' => $translatedContent,
        ]);
    }

    /**
     * Marcar job como fallido
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
