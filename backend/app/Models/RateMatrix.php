<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateMatrix extends Model
{
    use BelongsToSociety, HasAuditColumns;

    protected $table = 'rate_matrix';

    protected $fillable = [
        'society_id', 'unit_category_id', 'tariff_type_id', 'charge_head_id',
        'amount', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(UnitCategory::class, 'unit_category_id');
    }

    public function tariffType(): BelongsTo
    {
        return $this->belongsTo(TariffType::class);
    }
}
