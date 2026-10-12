<?php

namespace App\Http\Controllers\Api\V1\Units;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Shared CRUD for the simple society-scoped lookup tables (Block, Street,
 * UnitCategory, TariffType) - dynamic, admin-managed, "name" is the only
 * meaningful field beyond relations. See docs/schema.md.
 */
abstract class LookupController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected array $extraRules = [];

    public function index(Request $request)
    {
        $query = ($this->model)::query();
        if ($request->query('q')) {
            $query->where('name', 'like', '%'.$request->query('q').'%');
        }

        return $query->orderBy('name')->paginate($request->integer('per_page', 100));
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge(['name' => ['required', 'string', 'max:255']], $this->extraRules));
        $data['society_id'] = $request->user()->society_id;

        return response()->json(($this->model)::create($data), 201);
    }

    public function update(Request $request, int $id)
    {
        $record = ($this->model)::findOrFail($id);
        $data = $request->validate(array_merge(['name' => ['sometimes', 'string', 'max:255']], $this->extraRules));
        $record->update($data);

        return response()->json($record);
    }

    public function destroy(int $id)
    {
        ($this->model)::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
