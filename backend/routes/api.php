<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Billing\BillAdjustmentController;
use App\Http\Controllers\Api\V1\Billing\BillController;
use App\Http\Controllers\Api\V1\Billing\BillRunController;
use App\Http\Controllers\Api\V1\Billing\ChargeHeadController;
use App\Http\Controllers\Api\V1\Billing\RateMatrixController;
use App\Http\Controllers\Api\V1\Billing\UnitChargeOverrideController;
use App\Http\Controllers\Api\V1\ElectionController;
use App\Http\Controllers\Api\V1\OwnershipTransferController;
use App\Http\Controllers\Api\V1\Platform\AdCampaignController;
use App\Http\Controllers\Api\V1\Platform\AdvertiserController;
use App\Http\Controllers\Api\V1\Platform\PlatformReportController;
use App\Http\Controllers\Api\V1\Platform\SocietyController;
use App\Http\Controllers\Api\V1\Platform\SocietySubscriptionController;
use App\Http\Controllers\Api\V1\Platform\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\Units\BlockController;
use App\Http\Controllers\Api\V1\Units\StreetController;
use App\Http\Controllers\Api\V1\Units\TariffTypeController;
use App\Http\Controllers\Api\V1\Units\UnitCategoryController;
use App\Http\Controllers\Api\V1\Units\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // --- Public auth & no-OTP verification (rate limited) ---
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/staff/login', [AuthController::class, 'staffLogin']);
        Route::post('/auth/resident/login', [AuthController::class, 'residentLogin']);
        Route::post('/auth/platform/login', [AuthController::class, 'platformLogin']);
    });

    Route::middleware('throttle:verification')->group(function () {
        Route::get('/verification/societies', [VerificationController::class, 'societies']);
        Route::get('/verification/societies/{society}/units', [VerificationController::class, 'units']);
        Route::post('/verification/register', [VerificationController::class, 'register']);
    });

    // --- Authenticated (any guard: society users, residents, platform admins) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    // --- Society users only (staff + residents, not platform admins) ---
    Route::middleware(['auth:sanctum', 'society_user'])->group(function () {
        Route::post('/verification/link-unit', [VerificationController::class, 'linkAdditionalUnit']);

        // --- Units & Property (units.manage for writes, units.view for reads) ---
        Route::middleware('permission:units.view')->group(function () {
            Route::get('/units', [UnitController::class, 'index']);
            Route::get('/units/{unit}', [UnitController::class, 'show']);
            Route::get('/blocks', [BlockController::class, 'index']);
            Route::get('/streets', [StreetController::class, 'index']);
            Route::get('/unit-categories', [UnitCategoryController::class, 'index']);
            Route::get('/tariff-types', [TariffTypeController::class, 'index']);
        });

        Route::middleware('permission:units.manage')->group(function () {
            Route::post('/units', [UnitController::class, 'store']);
            Route::put('/units/{unit}', [UnitController::class, 'update']);
            Route::delete('/units/{unit}', [UnitController::class, 'destroy']);
            Route::post('/units/{unit}/release', [UnitController::class, 'releaseUnit']);

            Route::post('/blocks', [BlockController::class, 'store']);
            Route::put('/blocks/{id}', [BlockController::class, 'update']);
            Route::delete('/blocks/{id}', [BlockController::class, 'destroy']);

            Route::post('/streets', [StreetController::class, 'store']);
            Route::put('/streets/{id}', [StreetController::class, 'update']);
            Route::delete('/streets/{id}', [StreetController::class, 'destroy']);

            Route::post('/unit-categories', [UnitCategoryController::class, 'store']);
            Route::put('/unit-categories/{id}', [UnitCategoryController::class, 'update']);
            Route::delete('/unit-categories/{id}', [UnitCategoryController::class, 'destroy']);

            Route::post('/tariff-types', [TariffTypeController::class, 'store']);
            Route::put('/tariff-types/{id}', [TariffTypeController::class, 'update']);
            Route::delete('/tariff-types/{id}', [TariffTypeController::class, 'destroy']);
        });

        Route::middleware('permission:units.import')->post('/units/import', [UnitController::class, 'import']);

        // --- Ownership Transfers ---
        Route::middleware('permission:ownership_transfers.view')->group(function () {
            Route::get('/ownership-transfers', [OwnershipTransferController::class, 'index']);
            Route::get('/ownership-transfer-documents/{document}/download', [OwnershipTransferController::class, 'downloadDocument']);
        });
        Route::middleware('permission:ownership_transfers.manage')->group(function () {
            Route::post('/ownership-transfers', [OwnershipTransferController::class, 'store']);
            Route::post('/ownership-transfers/{ownershipTransfer}/documents', [OwnershipTransferController::class, 'uploadDocument']);
            Route::post('/ownership-transfers/{ownershipTransfer}/approve', [OwnershipTransferController::class, 'approve']);
            Route::post('/ownership-transfers/{ownershipTransfer}/reject', [OwnershipTransferController::class, 'reject']);
            Route::post('/ownership-transfers/{ownershipTransfer}/complete', [OwnershipTransferController::class, 'complete']);
        });

        // --- Elections / AGM ---
        Route::get('/elections', [ElectionController::class, 'index']);
        Route::get('/elections/{election}', [ElectionController::class, 'show']);
        Route::get('/elections/{election}/results', [ElectionController::class, 'results']);
        Route::post('/election-positions/{electionPosition}/nominate', [ElectionController::class, 'nominate']);
        Route::post('/election-positions/{electionPosition}/vote', [ElectionController::class, 'vote']);

        Route::middleware('permission:elections.manage')->group(function () {
            Route::post('/elections', [ElectionController::class, 'store']);
            Route::post('/elections/{election}/transition', [ElectionController::class, 'transition']);
            Route::post('/elections/{election}/certify-results', [ElectionController::class, 'certifyResults']);
            Route::post('/election-candidates/{electionCandidate}/review', [ElectionController::class, 'reviewCandidate']);
        });

        // --- Billing engine ---
        Route::middleware('permission:billing.view')->group(function () {
            Route::get('/charge-heads', [ChargeHeadController::class, 'index']);
            Route::get('/rate-matrix', [RateMatrixController::class, 'index']);
            Route::get('/rate-matrix/history', [RateMatrixController::class, 'history']);
            Route::get('/unit-charge-overrides', [UnitChargeOverrideController::class, 'index']);
            Route::get('/bill-adjustments', [BillAdjustmentController::class, 'index']);
            Route::get('/bill-runs', [BillRunController::class, 'index']);
            Route::get('/bill-runs/{billRun}', [BillRunController::class, 'show']);
            Route::get('/bill-runs/{billRun}/bulk-pdf/status', [BillRunController::class, 'bulkPdfStatus']);
            Route::get('/bill-runs/{billRun}/bulk-pdf/download', [BillRunController::class, 'downloadBulkPdf']);
            Route::get('/bills', [BillController::class, 'index']);
            Route::get('/bills/{bill}', [BillController::class, 'show']);
            Route::get('/bills/{bill}/pdf', [BillController::class, 'pdf']);
        });

        Route::middleware('permission:billing.charge_heads.manage')->group(function () {
            Route::post('/charge-heads', [ChargeHeadController::class, 'store']);
            Route::put('/charge-heads/{chargeHead}', [ChargeHeadController::class, 'update']);
            Route::delete('/charge-heads/{chargeHead}', [ChargeHeadController::class, 'destroy']);
        });

        Route::middleware('permission:billing.rate_matrix.manage')->group(function () {
            Route::post('/rate-matrix', [RateMatrixController::class, 'store']);
            Route::post('/rate-matrix/bulk', [RateMatrixController::class, 'bulkSet']);
        });

        Route::middleware('permission:billing.overrides.manage')->group(function () {
            Route::post('/unit-charge-overrides', [UnitChargeOverrideController::class, 'store']);
            Route::put('/unit-charge-overrides/{unitChargeOverride}', [UnitChargeOverrideController::class, 'update']);
            Route::delete('/unit-charge-overrides/{unitChargeOverride}', [UnitChargeOverrideController::class, 'destroy']);
        });

        Route::middleware('permission:billing.adjustments.manage')->group(function () {
            Route::post('/bill-adjustments', [BillAdjustmentController::class, 'store']);
            Route::delete('/bill-adjustments/{billAdjustment}', [BillAdjustmentController::class, 'destroy']);
        });

        Route::middleware('permission:billing.bill_runs.generate')->group(function () {
            Route::post('/bill-runs', [BillRunController::class, 'store']);
            Route::put('/bill-runs/{billRun}', [BillRunController::class, 'update']);
            Route::post('/bill-runs/{billRun}/generate', [BillRunController::class, 'generate']);
            Route::post('/bill-runs/{billRun}/regenerate', [BillRunController::class, 'regenerate']);
            Route::post('/bill-runs/{billRun}/bulk-pdf', [BillRunController::class, 'requestBulkPdf']);
        });

        Route::middleware('permission:billing.bill_runs.lock')->post('/bill-runs/{billRun}/lock', [BillRunController::class, 'lock']);

        // Further module route groups (complaints, SOS, ...) are
        // added here as each module is implemented - see PROGRESS.md.
    });

    // --- Platform admin only ---
    Route::middleware(['auth:sanctum', 'platform_admin'])->group(function () {
        Route::get('/platform/dashboard', [PlatformReportController::class, 'dashboard']);
        Route::get('/platform/audit-log', [PlatformReportController::class, 'auditLog']);

        Route::apiResource('platform/societies', SocietyController::class)->parameters(['societies' => 'society'])->except(['destroy']);
        Route::post('/platform/societies/{society}/suspend', [SocietyController::class, 'suspend']);
        Route::post('/platform/societies/{society}/activate', [SocietyController::class, 'activate']);

        Route::get('/platform/societies/{society}/subscriptions', [SocietySubscriptionController::class, 'index']);
        Route::post('/platform/societies/{society}/subscriptions', [SocietySubscriptionController::class, 'store']);
        Route::post('/platform/societies/{society}/subscriptions/{subscription}/cancel', [SocietySubscriptionController::class, 'cancel']);

        Route::apiResource('platform/subscription-plans', SubscriptionPlanController::class)->except(['show']);
        Route::apiResource('platform/advertisers', AdvertiserController::class)->except(['show']);
        Route::apiResource('platform/ad-campaigns', AdCampaignController::class)->except(['show']);
    });
});
