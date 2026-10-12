<?php

namespace App\Services;

use App\Models\Society;
use App\Models\Unit;

/**
 * Generates the unit's permanent reference_number: assigned once at
 * creation, never reused (see docs/decisions.md - Billing: rate
 * versioning). Format: {society_code}-{6-digit sequence}.
 */
class ReferenceNumberGenerator
{
    public function next(Society $society): string
    {
        $prefix = $society->code.'-';

        $lastSeq = Unit::withoutGlobalScopes()
            ->where('society_id', $society->id)
            ->where('reference_number', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTR(reference_number, ?) AS UNSIGNED)) as max_seq', [strlen($prefix) + 1])
            ->value('max_seq');

        $next = ((int) $lastSeq) + 1;
        $candidate = $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);

        while (Unit::withoutGlobalScopes()->where('reference_number', $candidate)->exists()) {
            $next++;
            $candidate = $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }
}
