<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Auto-fills created_by/updated_by from the authenticated user.
 * Apply alongside BelongsToSociety on every tenant-owned model.
 */
trait HasAuditColumns
{
    public static function bootHasAuditColumns(): void
    {
        static::creating(function ($model) {
            $userId = Auth::guard('sanctum')->id();

            if ($userId && empty($model->created_by)) {
                $model->created_by = $userId;
            }
            if ($userId && empty($model->updated_by)) {
                $model->updated_by = $userId;
            }
        });

        static::updating(function ($model) {
            $userId = Auth::guard('sanctum')->id();

            if ($userId) {
                $model->updated_by = $userId;
            }
        });
    }
}
