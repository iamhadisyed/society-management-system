<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Services\Billing\BillPdfService;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Bill::with('unit:id,unit_number,reference_number')
                ->when($request->query('unit_id'), fn ($q, $v) => $q->where('unit_id', $v))
                ->when($request->query('bill_run_id'), fn ($q, $v) => $q->where('bill_run_id', $v))
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('bill_number', 'like', "%{$v}%")->orWhere('reference_number', 'like', "%{$v}%")))
                ->orderByDesc('billing_month')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Bill $bill)
    {
        return response()->json($bill->load(['items', 'adjustments', 'unit:id,unit_number,reference_number']));
    }

    public function pdf(Bill $bill, BillPdfService $pdfService)
    {
        AuditLog::record('bill.printed', $bill);

        return $pdfService->render($bill)->stream("bill-{$bill->bill_number}.pdf");
    }
}
