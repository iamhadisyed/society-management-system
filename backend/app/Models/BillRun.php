<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillRun extends Model
{
    use BelongsToSociety, HasAuditColumns;

    protected $fillable = [
        'society_id', 'billing_month', 'status', 'announcement',
        'generated_at', 'locked_at', 'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'generated_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
