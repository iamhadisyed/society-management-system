<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitClaimDispute extends Model
{
    use BelongsToSociety;

    protected $fillable = [
        'society_id', 'unit_id', 'existing_user_id', 'claimant_name',
        'claimant_bill_number', 'claimant_password_hash', 'status',
        'resolved_by', 'resolved_at', 'resolution_notes',
    ];

    protected $hidden = ['claimant_password_hash'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
