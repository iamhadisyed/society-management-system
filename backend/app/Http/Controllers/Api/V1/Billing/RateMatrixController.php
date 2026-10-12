<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\RateMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rate Matrix: Unit Category x Tariff Type x Charge Head -> Amount,
 * versioned by effective_from (see docs/decisions.md - Billing). A
 * "set rate" call never edits an existing row in place; it closes the
 * currently-open row (effective_to = new effective_from - 1 day) and
 * inserts a new one, so historic bills always show the rate that
 * applied when they were generated.
 */
class RateMatrixController extends Controller
{
    /** Grid view: currently-active rate per (category, tariff, charge head). */
    public function index(Request $request)
    {
        $rates = RateMatrix::with(['category:id,name', 'tariffType:id,name', 'chargeHead:id,name,frequency'])
            ->whereNull('effective_to')
            ->when($request->query('unit_category_id'), fn ($q, $v) => $q->where('unit_category_id', $v))
            ->when($request->query('tariff_type_id'), fn ($q, $v) => $q->where('tariff_type_id', $v))
            ->get();

        return response()->json($rates);
    }

    public function history(Request $request)
    {
        $data = $request->validate([
            'unit_category_id' => ['required', 'integer'],
            'tariff_type_id' => ['required', 'integer'],
            'charge_head_id' => ['required', 'integer'],
        ]);

        $history = RateMatrix::where($data)->orderByDesc('effective_from')->get();

        return response()->json($history);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_category_id' => ['required', 'integer', 'exists:unit_categories,id'],
            'tariff_type_id' => ['required', 'integer', 'exists:tariff_types,id'],
            'charge_head_id' => ['required', 'integer', 'exists:charge_heads,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $effectiveFrom = Carbon::parse($data['effective_from'] ?? now())->startOfDay();

        $current = RateMatrix::where('unit_category_id', $data['unit_category_id'])
            ->where('tariff_type_id', $data['tariff_type_id'])
            ->where('charge_head_id', $data['charge_head_id'])
            ->whereNull('effective_to')
            ->first();

        if ($current) {
            $current->update(['effective_to' => $effectiveFrom->copy()->subDay()]);
        }

        $rate = RateMatrix::create([
            'society_id' => $request->user()->society_id,
            'unit_category_id' => $data['unit_category_id'],
            'tariff_type_id' => $data['tariff_type_id'],
            'charge_head_id' => $data['charge_head_id'],
            'amount' => $data['amount'],
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
        ]);

        return response()->json($rate, 201);
    }

    /** Grid bulk-set: apply several (category, tariff, charge_head) -> amount pairs in one call. */
    public function bulkSet(Request $request)
    {
        $data = $request->validate([
            'effective_from' => ['nullable', 'date'],
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.unit_category_id' => ['required', 'integer', 'exists:unit_categories,id'],
            'rates.*.tariff_type_id' => ['required', 'integer', 'exists:tariff_types,id'],
            'rates.*.charge_head_id' => ['required', 'integer', 'exists:charge_heads,id'],
            'rates.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $effectiveFrom = Carbon::parse($data['effective_from'] ?? now())->startOfDay();
        $societyId = $request->user()->society_id;

        $created = collect($data['rates'])->map(function ($row) use ($effectiveFrom, $societyId) {
            $current = RateMatrix::where('unit_category_id', $row['unit_category_id'])
                ->where('tariff_type_id', $row['tariff_type_id'])
                ->where('charge_head_id', $row['charge_head_id'])
                ->whereNull('effective_to')
                ->first();

            if ($current) {
                $current->update(['effective_to' => $effectiveFrom->copy()->subDay()]);
            }

            return RateMatrix::create([
                'society_id' => $societyId,
                'unit_category_id' => $row['unit_category_id'],
                'tariff_type_id' => $row['tariff_type_id'],
                'charge_head_id' => $row['charge_head_id'],
                'amount' => $row['amount'],
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
            ]);
        });

        return response()->json($created, 201);
    }
}
