<?php

namespace App\Jobs;

use App\Models\BillRun;
use App\Services\Billing\BillGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued (database driver, processed by the cPanel cron -> schedule:run
 * -> queue:work chain) so a large society's bill run doesn't blow the
 * shared-hosting request time limit. See docs/decisions.md.
 */
class GenerateBillRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $billRunId) {}

    public function handle(BillGenerationService $service): void
    {
        $billRun = BillRun::withoutGlobalScopes()->findOrFail($this->billRunId);

        if ($billRun->status !== 'draft') {
            return; // already generated/locked, or regenerate() already reset it back to draft
        }

        $service->generate($billRun);
    }
}
