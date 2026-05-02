<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para logs de un tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Crear log de auditoría
     */
    public static function log(
        string $action,
        ?int $tenantId = null,
        ?int $userId = null,
        array $metadata = [],
        ?string $ipAddress = null
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
        ]);
    }
}
