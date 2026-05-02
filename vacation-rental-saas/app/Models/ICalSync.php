<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ICalSync extends Model
{
    protected $fillable = [
        'property_id',
        'url_import',
        'last_sync_hash',
        'last_successful_sync',
        'is_active',
    ];

    protected $casts = [
        'last_successful_sync' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope para sincronizaciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
