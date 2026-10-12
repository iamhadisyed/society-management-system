<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillAdjustment;
use Illuminate\Http\Request;

/**
 * Standalone credit/debit adjustments raised against a unit. They sit
 * unapplied (bill_id null) until the next bill run, which folds them
 * into that bill's adjustments_total and stamps bill_id - see
 * BillGenerationService.
 */
class BillAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            BillAdjustment::when($request->query('unit_id'), fn ($q, $v) => $q->where('unit_id', $v))
                ->when($request->query('applied') !== null, fn ($q) => $q->when(
                    filter_var($request->query('applied'), FILTER_VALIDATE_BOOLEAN),
                    fn ($q2) => $q2->whereNotNull('bill_id'),
                    fn ($q2) => $q2->whereNull('bill_id'),
                ))
                ->latest()
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string'],
        ]);
        $data['society_id'] = $request->user()->society_id;

        return response()->json(BillAdjustment::create($data), 201);
    }

    public function destroy(BillAdjustment $billAdjustment)
    {
        abort_if($billAdjustment->bill_id !== null, 422, 'Cannot delete an adjustment already applied to a bill.');
        $billAdjustment->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
