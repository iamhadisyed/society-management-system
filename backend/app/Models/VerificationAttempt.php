<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Model;

class VerificationAttempt extends Model
{
    use BelongsToSociety;

    protected $fillable = [
        'society_id', 'unit_id', 'device_id', 'ip_address',
        'submitted_name', 'submitted_bill_number', 'matched',
    ];

    protected function casts(): array
    {
        return ['matched' => 'boolean'];
    }
}
