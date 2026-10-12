<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingVerification extends Model
{
    use BelongsToSociety;

    protected $fillable = [
        'society_id', 'unit_id', 'submitted_name', 'submitted_bill_number',
        'password_hash', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
