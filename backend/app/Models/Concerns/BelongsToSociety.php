<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SocietyScope;
use App\Models\Society;
use App\Support\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to every tenant-owned Eloquent model. Adds the global
 * SocietyScope and auto-fills society_id from the current tenant
 * context on create, so callers never have to pass it explicitly.
 */
trait BelongsToSociety
{
    public static function bootBelongsToSociety(): void
    {
        static::addGlobalScope(new SocietyScope);

        static::creating(function ($model) {
            if (empty($model->society_id) && Tenant::id() !== null) {
                $model->society_id = Tenant::id();
            }
        });
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }
}
