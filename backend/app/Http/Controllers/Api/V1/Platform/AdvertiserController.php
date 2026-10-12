<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\Advertiser;
use Illuminate\Http\Request;

/** Platform-level advertiser management (society_id null = platform advertiser). */
class AdvertiserController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Advertiser::whereNull('society_id')
                ->when($request->query('q'), fn ($q, $v) => $q->where('business_name', 'like', "%{$v}%"))
                ->orderBy('business_name')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(Advertiser::create($data), 201);
    }

    public function update(Request $request, Advertiser $advertiser)
    {
        $data = $request->validate([
            'business_name' => ['sometimes', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        $advertiser->update($data);

        return response()->json($advertiser);
    }

    public function destroy(Advertiser $advertiser)
    {
        $advertiser->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
