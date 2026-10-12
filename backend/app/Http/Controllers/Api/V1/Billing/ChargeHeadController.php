<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\ChargeHead;
use Illuminate\Http\Request;

class ChargeHeadController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            ChargeHead::when($request->query('q'), fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
                ->orderBy('name')
                ->paginate($request->integer('per_page', 100))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'in:monthly,quarterly,yearly,one_time'],
        ]);
        $data['society_id'] = $request->user()->society_id;

        return response()->json(ChargeHead::create($data), 201);
    }

    public function update(Request $request, ChargeHead $chargeHead)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'frequency' => ['sometimes', 'in:monthly,quarterly,yearly,one_time'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $chargeHead->update($data);

        return response()->json($chargeHead);
    }

    public function destroy(ChargeHead $chargeHead)
    {
        $chargeHead->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
