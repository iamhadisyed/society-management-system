<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $table = 'platform_audit_log';

    protected $fillable = ['platform_admin_id', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip_address'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }

    public static function record(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null): self
    {
        return self::create([
            'platform_admin_id' => auth('sanctum')->id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
        ]);
    }
}
