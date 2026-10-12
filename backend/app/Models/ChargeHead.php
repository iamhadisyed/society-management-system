<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeHead extends Model
{
    use BelongsToSociety, HasAuditColumns, SoftDeletes;

    protected $fillable = ['society_id', 'name', 'frequency', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Whether this charge head applies in the given billing month, given its frequency. */
    public function appliesInMonth(\Carbon\Carbon $billingMonth): bool
    {
        return match ($this->frequency) {
            'monthly' => true,
            'quarterly' => in_array($billingMonth->month, [1, 4, 7, 10], true),
            'yearly' => $billingMonth->month === 1,
            'one_time' => false, // one-time charges are raised as one_off_charges, not recurring rate_matrix entries
            default => false,
        };
    }
}
