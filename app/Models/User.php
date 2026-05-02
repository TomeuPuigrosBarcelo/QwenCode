<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'email',
        'password',
        'role',
        'is_super_admin',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'preferences' => 'array',
        'is_super_admin' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id'); // Como huésped
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Verificar si es SuperAdmin global
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin || $this->role === 'super_admin';
    }

    /**
     * Verificar si es propietario del tenant
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Verificar si es staff del tenant
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }
}
