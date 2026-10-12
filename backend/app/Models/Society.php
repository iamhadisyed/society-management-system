<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Society extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'logo_path', 'address', 'city', 'contact_phone', 'contact_email',
        'bank_name', 'bank_account_number', 'bank_iban', 'status', 'timezone',
        'default_language', 'settings', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** Default per-society settings, overridden by the `settings` JSON column. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SocietySubscription::class);
    }
}
