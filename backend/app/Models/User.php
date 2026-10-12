<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * NOTE: deliberately does NOT use the BelongsToSociety trait / SocietyScope.
 * Sanctum resolves the authenticated user by querying this model, and
 * SocietyScope resolves the tenant by reading the authenticated user
 * (Tenant::id() -> Auth::guard('sanctum')->user()) - applying the scope
 * here creates infinite recursion during auth resolution. Society-scoped
 * user listings filter by society_id explicitly in the controller/service
 * instead (see docs/decisions.md).
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'society_id', 'user_type', 'name', 'email', 'phone', 'password',
        'language', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /** Units this resident is linked to (unit switcher). */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'linked_user_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function isSocietyStaff(): bool
    {
        return $this->user_type === 'society_staff';
    }

    public function isResident(): bool
    {
        return $this->user_type === 'resident';
    }

    /** Flattened list of permission keys across all assigned roles, cached per-request. */
    public function permissionKeys(): array
    {
        return $this->relationLoaded('roles')
            ? $this->roles->flatMap(fn ($role) => $role->relationLoaded('permissions') ? $role->permissions->pluck('key') : $role->permissions()->pluck('key'))->unique()->values()->all()
            : $this->roles()->with('permissions')->get()->flatMap(fn ($role) => $role->permissions->pluck('key'))->unique()->values()->all();
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissionKeys(), true);
    }
}
