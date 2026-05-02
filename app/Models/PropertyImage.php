<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'url',
        'sort_order',
        'alt_text_key',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Obtener texto alt traducido
     */
    public function getTranslatedAltText(string $locale = 'es'): ?string
    {
        if (!$this->alt_text_key) {
            return null;
        }

        // Buscar traducción usando alt_text_key como referencia
        return Translation::where('tenant_id', $this->property->tenant_id)
            ->where('entity_type', 'image_alt')
            ->where('field_key', $this->alt_text_key)
            ->where('locale', $locale)
            ->value('value');
    }
}
