<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class EmailTemplate extends Model
{
    use HasFactory;

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

    /**
     * Relación polimórfica para traducciones
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'entity')
            ->where('entity_type', 'email_template')
            ->where('entity_id', $this->id);
    }

    /**
     * Obtener asunto traducido
     */
    public function getTranslatedSubject(string $locale = 'es'): ?string
    {
        return $this->translations()
            ->where('field_key', 'subject')
            ->where('locale', $locale)
            ->value('value');
    }

    /**
     * Obtener cuerpo traducido
     */
    public function getTranslatedBody(string $locale = 'es'): ?string
    {
        return $this->translations()
            ->where('field_key', 'body')
            ->where('locale', $locale)
            ->value('value');
    }

    /**
     * Scope para templates activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
