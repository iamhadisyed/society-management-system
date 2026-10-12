<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Support\Tenancy\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes create/update/delete/restore events for this model to the
 * audit_logs table (who did what, old/new values, IP), per the spec's
 * "full audit log" requirement. Apply to any tenant model whose
 * changes should be traceable (bills, payments, complaints, roles,
 * expenses, ...) - not every lookup table needs it.
 *
 * For business actions that aren't plain CRUD (approve, waive, collect,
 * print, lock), call AuditLog::record() directly from the service/
 * controller instead of relying on this trait's create/update hooks.
 */
trait LogsAuditTrail
{
    public static function bootLogsAuditTrail(): void
    {
        static::created(fn ($model) => $model->writeAuditLog('created', null, $model->getAttributes()));

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at'], $changes['updated_by']);
            if (empty($changes)) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAuditLog('updated', $old, $changes);
        });

        static::deleted(fn ($model) => $model->writeAuditLog('deleted', $model->getAttributes(), null));
    }

    protected function writeAuditLog(string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'society_id' => $this->society_id ?? Tenant::id(),
            'user_id' => Auth::guard('sanctum')->id(),
            'action' => class_basename($this).'.'.$action,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
