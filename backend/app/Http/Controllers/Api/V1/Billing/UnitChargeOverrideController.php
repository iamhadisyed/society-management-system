<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\UnitChargeOverride;
use Illuminate\Http\Request;

class UnitChargeOverrideController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            UnitChargeOverride::with(['chargeHead:id,name'])
                ->when($request->query('unit_id'), fn ($q, $v) => $q->where('unit_id', $v))
                ->latest()
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'charge_head_id' => ['required', 'integer', 'exists:charge_heads,id'],
            'type' => ['required', 'in:extra,waiver'],
            'value_type' => ['required', 'in:fixed,percent'],
            'value' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);
        $data['society_id'] = $request->user()->society_id;
        $data['approved_by'] = $request->user()->id;
        $data['is_active'] = true;

        return response()->json(UnitChargeOverride::create($data), 201);
    }

    public function update(Request $request, UnitChargeOverride $unitChargeOverride)
    {
        $data = $request->validate([
            'value' => ['sometimes', 'numeric', 'min:0'],
            'effective_to' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $unitChargeOverride->update($data);

        return response()->json($unitChargeOverride);
    }

    public function destroy(UnitChargeOverride $unitChargeOverride)
    {
        $unitChargeOverride->update(['is_active' => false]);

        return response()->json(['message' => 'Deactivated.']);
    }
}
