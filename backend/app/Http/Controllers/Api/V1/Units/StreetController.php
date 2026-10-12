<?php

namespace App\Http\Controllers\Api\V1\Units;

use App\Models\Street;
use Illuminate\Http\Request;

class StreetController extends LookupController
{
    protected string $model = Street::class;

    protected array $extraRules = ['block_id' => ['required', 'integer', 'exists:blocks,id']];

    public function index(Request $request)
    {
        $query = Street::query()->when($request->query('block_id'), fn ($q, $v) => $q->where('block_id', $v));
        if ($request->query('q')) {
            $query->where('name', 'like', '%'.$request->query('q').'%');
        }

        return $query->orderBy('name')->paginate($request->integer('per_page', 100));
    }
}
