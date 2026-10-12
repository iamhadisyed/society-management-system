<?php

namespace App\Services\Billing;

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\BillItem;
use App\Models\BillRun;
use App\Models\ChargeHead;
use App\Models\OneOffCharge;
use App\Models\RateMatrix;
use App\Models\Unit;
use App\Models\UnitChargeOverride;
use App\Support\Tenancy\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates bills for every active unit in a society for one billing
 * run/month. Chunked (see generate()) so it stays within shared-hosting
 * time limits when queued - see docs/decisions.md "Hosting/queues".
 *
 * Idempotent per run: regenerate() wipes and redoes a draft/generated
 * (not locked) run's bills, and returning here after a partial failure
 * simply re-runs from unit 1 since nothing is left half-applied.
 */
class BillGenerationService
{
    public function generate(BillRun $billRun): void
    {
        $society = $billRun->society;
        $billingMonth = Carbon::parse($billRun->billing_month)->startOfMonth();
        $dueDays = (int) ($society->setting('bill_due_days') ?? 15);
        $issuedOn = now()->toDateString();
        $dueDate = now()->addDays($dueDays)->toDateString();

        $chargeHeads = ChargeHead::where('is_active', true)->get()
            ->filter(fn (ChargeHead $ch) => $ch->appliesInMonth($billingMonth));

        Tenant::run($society->id, function () use ($billRun, $society, $billingMonth, $issuedOn, $dueDate, $chargeHeads) {
            $sequence = $billRun->bills()->count();

            Unit::where('is_active', true)->orderBy('id')->chunkById(100, function ($units) use (
                $billRun, $society, $billingMonth, $issuedOn, $dueDate, $chargeHeads, &$sequence
            ) {
                foreach ($units as $unit) {
                    $sequence++;
                    $this->generateForUnit($billRun, $unit, $society, $billingMonth, $issuedOn, $dueDate, $chargeHeads, $sequence);
                }
            });

            $billRun->update(['status' => 'generated', 'generated_at' => now()]);
        });
    }

    private function generateForUnit(BillRun $billRun, Unit $unit, $society, Carbon $billingMonth, string $issuedOn, string $dueDate, $chargeHeads, int $sequence): void
    {
        DB::transaction(function () use ($billRun, $unit, $society, $billingMonth, $issuedOn, $dueDate, $chargeHeads, $sequence) {
            $billNumber = sprintf('%s-%s-%04d', $society->code, $billingMonth->format('Ym'), $sequence);

            $overrides = UnitChargeOverride::where('unit_id', $unit->id)->get();

            $serial = 0;
            $thisMonthTotal = 0.0;
            $items = [];

            foreach ($chargeHeads as $chargeHead) {
                $rate = RateMatrix::where('unit_category_id', $unit->unit_category_id)
                    ->where('tariff_type_id', $unit->tariff_type_id)
                    ->where('charge_head_id', $chargeHead->id)
                    ->where('effective_from', '<=', $billingMonth)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $billingMonth))
                    ->orderByDesc('effective_from')
                    ->first();

                if (! $rate) {
                    continue; // charge head not configured for this category/tariff combination
                }

                $amount = (float) $rate->amount;
                foreach ($overrides as $override) {
                    if ($override->charge_head_id === $chargeHead->id && $override->appliesOn($billingMonth)) {
                        $amount += $override->amountFor((float) $rate->amount);
                    }
                }
                $amount = max(0, $amount);

                $serial++;
                $items[] = ['charge_head_id' => $chargeHead->id, 'serial' => $serial, 'description' => $chargeHead->name, 'amount' => $amount];
                $thisMonthTotal += $amount;
            }

            // One-off charges pending for this unit (form fees, booking fees, transfer fees, fines, ...)
            $oneOffCharges = OneOffCharge::where('unit_id', $unit->id)->where('status', 'pending')->get();
            foreach ($oneOffCharges as $charge) {
                $serial++;
                $items[] = ['charge_head_id' => null, 'serial' => $serial, 'description' => $charge->title, 'amount' => (float) $charge->amount];
                $thisMonthTotal += (float) $charge->amount;
            }

            // Arrears from the unit's previous bill (outstanding balance, surcharge-inclusive if it went overdue)
            $previousBill = Bill::where('unit_id', $unit->id)->orderByDesc('billing_month')->first();
            $arrears = $previousBill ? $previousBill->outstandingBalance() : 0.0;

            // Unapplied standalone adjustments (credit/debit) raised against this unit
            $pendingAdjustments = BillAdjustment::where('unit_id', $unit->id)->whereNull('bill_id')->get();
            $adjustmentsTotal = $pendingAdjustments->sum(fn (BillAdjustment $a) => $a->signedAmount());

            $payableWithinDue = max(0, $thisMonthTotal + $arrears + $adjustmentsTotal);

            $surchargeType = $society->setting('surcharge_type') ?? 'fixed';
            $surchargeValue = (float) ($society->setting('surcharge_value') ?? 0);
            $surchargeAmount = $surchargeType === 'percent' ? round($payableWithinDue * $surchargeValue / 100, 2) : $surchargeValue;

            $bill = Bill::create([
                'society_id' => $society->id,
                'bill_run_id' => $billRun->id,
                'unit_id' => $unit->id,
                'bill_number' => $billNumber,
                'reference_number' => $unit->reference_number,
                'billing_month' => $billingMonth,
                'issued_on' => $issuedOn,
                'due_date' => $dueDate,
                'arrears' => $arrears,
                'this_month_total' => $thisMonthTotal,
                'adjustments_total' => $adjustmentsTotal,
                'surcharge_amount' => $surchargeAmount,
                'payable_within_due' => $payableWithinDue,
                'payable_after_due' => $payableWithinDue + $surchargeAmount,
                'amount_paid' => 0,
                'status' => 'unpaid',
                'app_linked_snapshot' => $unit->isLinked(),
                'app_linked_since_snapshot' => $unit->linked_at,
                'owner_name_snapshot' => $unit->owner_name,
                'occupant_name_snapshot' => $unit->occupant_name,
                'address_snapshot' => $unit->full_address,
                'residence_status_snapshot' => $unit->residence_status,
                'tariff_type_snapshot' => $unit->tariffType?->name,
            ]);

            foreach ($items as $item) {
                BillItem::create(['bill_id' => $bill->id, ...$item]);
            }

            OneOffCharge::whereIn('id', $oneOffCharges->pluck('id'))->update(['applied_bill_id' => $bill->id, 'status' => 'applied']);
            BillAdjustment::whereIn('id', $pendingAdjustments->pluck('id'))->update(['bill_id' => $bill->id]);

            $unit->forceFill(['current_bill_number' => $billNumber])->save();
        });
    }
}
