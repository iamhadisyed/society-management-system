<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdImpression;
use App\Models\PlatformAuditLog;
use App\Models\Society;
use App\Models\SocietySubscription;
use Illuminate\Http\Request;

class PlatformReportController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'societies_count' => Society::count(),
            'active_societies_count' => Society::where('status', 'active')->count(),
            'suspended_societies_count' => Society::where('status', 'suspended')->count(),
            'active_subscriptions_count' => SocietySubscription::where('status', 'active')->where('end_date', '>=', now())->count(),
            'platform_revenue' => SocietySubscription::where('status', 'active')->sum('price_at_signup'),
            'ad_campaigns_active_count' => AdCampaign::where('scope', 'platform')->where('is_active', true)->where('ends_at', '>=', now())->count(),
            'ad_impressions_total' => AdImpression::count(),
            'ad_clicks_total' => AdClick::count(),
        ]);
    }

    public function auditLog(Request $request)
    {
        return response()->json(
            PlatformAuditLog::with('platformAdmin:id,name,email')
                ->latest()
                ->paginate($request->integer('per_page', 50))
        );
    }
}
