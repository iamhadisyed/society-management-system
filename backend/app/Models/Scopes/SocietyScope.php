<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically scopes every query on a tenant-owned model to the
 * current authenticated user's society_id, so no controller/service
 * has to remember to add ->where('society_id', ...) itself.
 *
 * Platform administrators (guard: platform_admin) are not tied to a
 * society, so the scope is a no-op for that guard - platform-level
 * endpoints must filter by society_id explicitly when needed.
 */
class SocietyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Tenant::id() !== null) {
            $builder->where($model->getTable().'.society_id', Tenant::id());
        }
    }
}
