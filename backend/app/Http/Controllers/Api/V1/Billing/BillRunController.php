<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBillRunJob;
use App\Jobs\GenerateBillRunPdfJob;
use App\Models\AuditLog;
use App\Models\BillAdjustment;
use App\Models\BillRun;
use App\Models\OneOffCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BillRunController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            BillRun::withCount('bills')
                ->orderByDesc('billing_month')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(BillRun $billRun)
    {
        return response()->json($billRun->loadCount('bills'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['billing_month' => ['required', 'date']]);
        $billingMonth = Carbon::parse($data['billing_month'])->startOfMonth();

        $billRun = BillRun::create([
            'society_id' => $request->user()->society_id,
            'billing_month' => $billingMonth,
            'status' => 'draft',
        ]);

        return response()->json($billRun, 201);
    }

    public function update(Request $request, BillRun $billRun)
    {
        abort_if($billRun->status === 'locked', 422, 'Locked bill runs cannot be edited.');

        $data = $request->validate(['announcement' => ['nullable', 'string']]);
        $billRun->update($data);

        return response()->json($billRun);
    }

    /** Dispatches the chunked, queued generation job - see GenerateBillRunJob. */
    public function generate(BillRun $billRun)
    {
        abort_unless($billRun->status === 'draft', 422, 'Only a draft run can be generated.');

        GenerateBillRunJob::dispatch($billRun->id);

        return response()->json(['message' => 'Bill generation queued.']);
    }

    /** Wipes this run's bills (undoing arrears/adjustment/one-off application) and re-queues generation. */
    public function regenerate(BillRun $billRun)
    {
        abort_if($billRun->status === 'locked', 422, 'Locked bill runs cannot be regenerated.');

        DB::transaction(function () use ($billRun) {
            $billIds = $billRun->bills()->pluck('id');

            BillAdjustment::whereIn('bill_id', $billIds)->update(['bill_id' => null]);
            OneOffCharge::whereIn('applied_bill_id', $billIds)->update(['applied_bill_id' => null, 'status' => 'pending']);
            $billRun->bills()->delete(); // bill_items cascade via FK

            $billRun->update(['status' => 'draft', 'generated_at' => null]);
        });

        GenerateBillRunJob::dispatch($billRun->id);

        return response()->json(['message' => 'Bill run reset and regeneration queued.']);
    }

    public function lock(Request $request, BillRun $billRun)
    {
        abort_unless($billRun->status === 'generated', 422, 'Only a generated run can be locked.');

        $billRun->update(['status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()->id]);

        AuditLog::record('bill_run.locked', $billRun);

        return response()->json($billRun);
    }

    public function requestBulkPdf(BillRun $billRun)
    {
        abort_if($billRun->status === 'draft', 422, 'Generate the bills before requesting a bulk PDF.');

        Storage::disk('local')->delete("bill-runs/{$billRun->id}/bulk.pdf");
        GenerateBillRunPdfJob::dispatch($billRun->id);

        return response()->json(['message' => 'Bulk PDF generation queued.']);
    }

    public function bulkPdfStatus(BillRun $billRun)
    {
        return response()->json(['ready' => Storage::disk('local')->exists("bill-runs/{$billRun->id}/bulk.pdf")]);
    }

    public function downloadBulkPdf(BillRun $billRun)
    {
        $path = "bill-runs/{$billRun->id}/bulk.pdf";
        abort_unless(Storage::disk('local')->exists($path), 404, 'Bulk PDF not ready yet.');

        AuditLog::record('bill_run.bulk_pdf_downloaded', $billRun);

        return Storage::disk('local')->download($path, "bills-{$billRun->billing_month->format('Y-m')}.pdf");
    }
}
