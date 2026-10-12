<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'society_id', 'user_id', 'action', 'subject_type', 'subject_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a non-CRUD business action (approve, waive, collect, lock,
     * print, ...) that LogsAuditTrail's create/update hooks don't cover.
     */
    public static function record(string $action, ?Model $subject, ?array $old = null, ?array $new = null): self
    {
        return self::create([
            'society_id' => $subject?->society_id ?? \App\Support\Tenancy\Tenant::id(),
            'user_id' => auth('sanctum')->id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
