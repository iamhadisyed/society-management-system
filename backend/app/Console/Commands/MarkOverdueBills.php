<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Society;
use App\Support\Tenancy\Tenant;
use Illuminate\Console\Command;

/**
 * Daily: bills past their due_date and still unpaid/partially_paid move
 * to 'overdue'. The surcharge_amount was already computed at generation
 * time (see BillGenerationService); this only flips the status so
 * outstandingBalance() starts including it, and so the next bill run's
 * arrears calculation picks up the surcharge-inclusive balance.
 */
class MarkOverdueBills extends Command
{
    protected $signature = 'bills:mark-overdue';

    protected $description = 'Mark unpaid/partially-paid bills past their due date as overdue';

    public function handle(): int
    {
        $total = 0;

        Society::where('status', 'active')->each(function (Society $society) use (&$total) {
            Tenant::run($society->id, function () use ($society, &$total) {
                $count = Bill::whereIn('status', ['unpaid', 'partially_paid'])
                    ->where('due_date', '<', now()->toDateString())
                    ->update(['status' => 'overdue']);
                $total += $count;
            });
        });

        $this->info("Marked {$total} bill(s) overdue across all active societies.");

        return self::SUCCESS;
    }
}
