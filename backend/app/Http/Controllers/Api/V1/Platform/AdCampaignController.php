<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Platform-run ad campaigns (scope=platform): cross-society or targeted
 * to selected societies. Society-run campaigns (scope=society) are
 * managed from the Society panel's own Ads module, not here.
 */
class AdCampaignController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            AdCampaign::with(['advertiser', 'placements'])
                ->where('scope', 'platform')
                ->withCount(['placements'])
                ->orderByDesc('starts_at')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'advertiser_id' => ['required', 'integer', 'exists:advertisers,id'],
            'title' => ['required', 'string', 'max:255'],
            'creative' => ['required', 'image', 'max:5120'],
            'link_url' => ['nullable', 'url'],
            'link_phone' => ['nullable', 'string', 'max:20'],
            'link_whatsapp' => ['nullable', 'string', 'max:20'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'target_society_ids' => ['nullable', 'array'],
            'target_society_ids.*' => ['integer', 'exists:societies,id'],
            'placements' => ['required', 'array', 'min:1'],
            'placements.*' => ['in:home_carousel,ad_list,bill_pdf'],
        ]);

        $path = $request->file('creative')->store('ad-creatives', 'public');

        $campaign = AdCampaign::create([
            'advertiser_id' => $data['advertiser_id'],
            'owner_society_id' => null,
            'title' => $data['title'],
            'creative_path' => $path,
            'link_url' => $data['link_url'] ?? null,
            'link_phone' => $data['link_phone'] ?? null,
            'link_whatsapp' => $data['link_whatsapp'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'scope' => 'platform',
            'target_society_ids' => $data['target_society_ids'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        foreach ($data['placements'] as $i => $placement) {
            $campaign->placements()->create(['placement' => $placement, 'sort_order' => $i]);
        }

        PlatformAuditLog::record('ad_campaign.created', $campaign, null, $campaign->toArray());

        return response()->json($campaign->load('placements'), 201);
    }

    public function update(Request $request, AdCampaign $adCampaign)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'link_url' => ['nullable', 'url'],
            'ends_at' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $adCampaign->update($data + ['updated_by' => $request->user()->id]);

        return response()->json($adCampaign);
    }

    public function destroy(AdCampaign $adCampaign)
    {
        Storage::disk('public')->delete($adCampaign->creative_path);
        $adCampaign->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
