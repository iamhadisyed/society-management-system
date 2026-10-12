<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneOffCharge extends Model
{
    use BelongsToSociety, HasAuditColumns;

    protected $fillable = [
        'society_id', 'unit_id', 'title', 'amount', 'reason',
        'source_type', 'source_id', 'applied_bill_id', 'status',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
