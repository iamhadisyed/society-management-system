<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillAdjustment extends Model
{
    use BelongsToSociety, HasAuditColumns;

    protected $fillable = ['society_id', 'unit_id', 'bill_id', 'type', 'amount', 'reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** Signed contribution to a bill total: credit reduces what's owed, debit increases it. */
    public function signedAmount(): float
    {
        return $this->type === 'credit' ? -(float) $this->amount : (float) $this->amount;
    }
}
