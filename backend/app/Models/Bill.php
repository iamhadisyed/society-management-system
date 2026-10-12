<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use BelongsToSociety, HasAuditColumns, LogsAuditTrail;

    protected $fillable = [
        'society_id', 'bill_run_id', 'unit_id', 'bill_number', 'reference_number',
        'billing_month', 'issued_on', 'due_date', 'arrears', 'this_month_total',
        'adjustments_total', 'surcharge_amount', 'payable_within_due', 'payable_after_due',
        'amount_paid', 'status', 'app_linked_snapshot', 'app_linked_since_snapshot',
        'owner_name_snapshot', 'occupant_name_snapshot', 'address_snapshot',
        'residence_status_snapshot', 'tariff_type_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'issued_on' => 'date',
            'due_date' => 'date',
            'arrears' => 'decimal:2',
            'this_month_total' => 'decimal:2',
            'adjustments_total' => 'decimal:2',
            'surcharge_amount' => 'decimal:2',
            'payable_within_due' => 'decimal:2',
            'payable_after_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'app_linked_snapshot' => 'boolean',
            'app_linked_since_snapshot' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function billRun(): BelongsTo
    {
        return $this->belongsTo(BillRun::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BillAdjustment::class);
    }

    /** What's currently owed on this bill (post-due surcharge included once overdue). */
    public function outstandingBalance(): float
    {
        $total = $this->status === 'overdue' ? (float) $this->payable_after_due : (float) $this->payable_within_due;

        return max(0, $total - (float) $this->amount_paid);
    }

    public function isPastDue(): bool
    {
        return $this->due_date->isPast() && in_array($this->status, ['unpaid', 'partially_paid'], true);
    }
}
