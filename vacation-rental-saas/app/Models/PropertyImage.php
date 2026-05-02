<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyImage extends Model
{
    protected $fillable = [
        'property_id',
        'url',
        'sort_order',
        'alt_text_key',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Obtener texto alternativo traducido
     */
    public function getAltTextAttribute(): ?string
    {
        if (!$this->alt_text_key) {
            return null;
        }

        $translation = Translation::where('entity_type', 'image_alt')
            ->where('entity_id', $this->id)
            ->where('field_key', 'alt_text')
            ->where('locale', $this->property->tenant->default_locale)
            ->first();

        return $translation?->value;
    }
}
