<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitChargeOverride extends Model
{
    use BelongsToSociety, HasAuditColumns;

    protected $fillable = [
        'society_id', 'unit_id', 'charge_head_id', 'type', 'value_type', 'value',
        'reason', 'approved_by', 'effective_from', 'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class);
    }

    public function appliesOn(\Carbon\Carbon $date): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->effective_from && $date->lt($this->effective_from)) {
            return false;
        }
        if ($this->effective_to && $date->gt($this->effective_to)) {
            return false;
        }

        return true;
    }

    /** Signed delta this override contributes to a base amount (positive=extra, negative=waiver). */
    public function amountFor(float $baseAmount): float
    {
        $magnitude = $this->value_type === 'percent' ? $baseAmount * ((float) $this->value / 100) : (float) $this->value;

        return $this->type === 'waiver' ? -$magnitude : $magnitude;
    }
}
