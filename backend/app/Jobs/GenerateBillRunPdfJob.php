<?php

namespace App\Jobs;

use App\Models\AdCampaign;
use App\Models\BillRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Bulk bill-run PDF, generated in the background so a large run doesn't
 * hit the shared-hosting request time limit (see docs/decisions.md).
 * Written to the private disk at bill-runs/{id}/bulk.pdf; the panel
 * polls BillRunController::bulkPdfStatus() and downloads once ready.
 */
class GenerateBillRunPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $billRunId) {}

    public function handle(): void
    {
        $billRun = BillRun::withoutGlobalScopes()->with('society')->findOrFail($this->billRunId);
        $society = $billRun->society;

        $ad = AdCampaign::visibleToSociety($society->id)
            ->whereHas('placements', fn ($q) => $q->where('placement', 'bill_pdf'))
            ->first();

        $bills = $billRun->bills()->with('items')->orderBy('id')->get();

        $qrSvgBase64 = [];
        foreach ($bills as $bill) {
            $payload = json_encode([
                'society_code' => $society->code,
                'reference_number' => $bill->reference_number,
                'bill_number' => $bill->bill_number,
                'amount' => (float) $bill->payable_within_due,
            ]);
            $qrSvgBase64[$bill->id] = base64_encode(QrCode::format('svg')->size(120)->generate($payload));
        }

        $html = view('pdf.bill-run', [
            'bills' => $bills,
            'society' => $society,
            'qrSvgBase64' => $qrSvgBase64,
            'ad' => $ad,
            'printedBy' => 'System (bulk export)',
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();

        Storage::disk('local')->put("bill-runs/{$billRun->id}/bulk.pdf", $pdf);
    }
}
