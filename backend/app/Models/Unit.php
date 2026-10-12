<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use BelongsToSociety, HasAuditColumns, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'society_id', 'block_id', 'street_id', 'unit_number', 'full_address',
        'unit_category_id', 'tariff_type_id', 'residence_status', 'owner_name',
        'occupant_name', 'reference_number', 'current_bill_number',
        'linked_user_id', 'linked_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(UnitCategory::class, 'unit_category_id');
    }

    public function tariffType(): BelongsTo
    {
        return $this->belongsTo(TariffType::class);
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function isLinked(): bool
    {
        return $this->linked_user_id !== null;
    }
}
