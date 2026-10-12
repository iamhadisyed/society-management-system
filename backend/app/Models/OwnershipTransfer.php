<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnershipTransfer extends Model
{
    use BelongsToSociety, HasAuditColumns, LogsAuditTrail;

    protected $fillable = [
        'society_id', 'unit_id', 'transfer_type', 'previous_owner_name', 'previous_owner_cnic',
        'new_owner_name', 'new_owner_cnic', 'new_owner_phone', 'transfer_date', 'sale_price',
        'transfer_fee', 'one_off_charge_id', 'status', 'rejection_reason',
        'approved_by', 'approved_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'sale_price' => 'decimal:2',
            'transfer_fee' => 'decimal:2',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OwnershipTransferDocument::class);
    }
}
