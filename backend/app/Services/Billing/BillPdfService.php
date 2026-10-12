<?php

namespace App\Services\Billing;

use App\Models\AdCampaign;
use App\Models\Bill;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Renders the 3-copy (Resident/Bank/Society) bill PDF per spec: header
 * with bank details + "Scan to Pay" QR, billing details table,
 * announcement box, ad slot, and the app-linked-status anti-fraud line.
 *
 * Urdu text: dompdf needs a real font file to shape Urdu correctly.
 * Place a Noto Nastaliq Urdu .ttf under resources/fonts/ and register it
 * in config/dompdf.php's font family list; until then this falls back
 * to DomPDF's bundled DejaVu Sans, which renders Urdu characters but
 * without proper Nastaliq shaping/ligatures (documented in
 * docs/decisions.md - this repo's sandbox can't fetch binary font
 * files, so the .ttf must be added by whoever deploys this).
 */
class BillPdfService
{
    public function render(Bill $bill): \Barryvdh\DomPDF\PDF
    {
        $bill->loadMissing(['unit.block', 'unit.street', 'items', 'billRun', 'society' => fn ($q) => $q]);
        $society = $bill->society;

        $qrPayload = json_encode([
            'society_code' => $society->code,
            'reference_number' => $bill->reference_number,
            'bill_number' => $bill->bill_number,
            'amount' => (float) $bill->payable_within_due,
        ]);
        $qrSvg = base64_encode(QrCode::format('svg')->size(120)->generate($qrPayload));

        $ad = AdCampaign::visibleToSociety($society->id)
            ->whereHas('placements', fn ($q) => $q->where('placement', 'bill_pdf'))
            ->first();

        $html = view('pdf.bill', [
            'bill' => $bill,
            'society' => $society,
            'qrSvgBase64' => $qrSvg,
            'ad' => $ad,
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait');
    }
}
