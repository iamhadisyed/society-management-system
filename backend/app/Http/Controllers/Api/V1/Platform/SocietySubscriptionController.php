<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\Society;
use App\Models\SocietySubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SocietySubscriptionController extends Controller
{
    public function index(Society $society)
    {
        return response()->json($society->subscriptions()->with('plan')->latest('start_date')->get());
    }

    public function store(Request $request, Society $society)
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

        $subscription = SocietySubscription::create([
            'society_id' => $society->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'active',
            'price_at_signup' => $plan->price,
        ]);

        PlatformAuditLog::record('society_subscription.created', $subscription, null, $subscription->toArray());

        return response()->json($subscription->load('plan'), 201);
    }

    public function cancel(Society $society, SocietySubscription $subscription)
    {
        abort_unless($subscription->society_id === $society->id, 404);

        $subscription->update(['status' => 'cancelled']);
        PlatformAuditLog::record('society_subscription.cancelled', $subscription);

        return response()->json($subscription);
    }
}
