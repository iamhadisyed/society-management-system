<?php

namespace App\Http\Controllers\Api\V1\Units;

use App\Http\Controllers\Controller;
use App\Http\Requests\Units\StoreUnitRequest;
use App\Http\Resources\UnitResource;
use App\Imports\UnitsImport;
use App\Models\AuditLog;
use App\Models\Unit;
use App\Services\ReferenceNumberGenerator;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = Unit::query()
            ->with(['block', 'street', 'category', 'tariffType'])
            ->when($request->query('q'), function ($query, $q) {
                $query->where(function ($w) use ($q) {
                    $w->where('unit_number', 'like', "%{$q}%")
                        ->orWhere('reference_number', 'like', "%{$q}%")
                        ->orWhere('owner_name', 'like', "%{$q}%")
                        ->orWhere('full_address', 'like', "%{$q}%");
                });
            })
            ->when($request->query('block_id'), fn ($query, $v) => $query->where('block_id', $v))
            ->when($request->query('street_id'), fn ($query, $v) => $query->where('street_id', $v))
            ->when($request->query('is_linked') !== null, fn ($query) => $query->when(
                filter_var($request->query('is_linked'), FILTER_VALIDATE_BOOLEAN),
                fn ($q2) => $q2->whereNotNull('linked_user_id'),
                fn ($q2) => $q2->whereNull('linked_user_id'),
            ))
            ->when($request->query('is_active') !== null, fn ($query, $v) => $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('unit_number')
            ->paginate($request->integer('per_page', 25));

        return UnitResource::collection($units);
    }

    public function show(Unit $unit)
    {
        return new UnitResource($unit->load(['block', 'street', 'category', 'tariffType', 'linkedUser']));
    }

    public function store(StoreUnitRequest $request, ReferenceNumberGenerator $generator)
    {
        $unit = new Unit($request->validated());
        $unit->society_id = $request->user()->society_id;
        $unit->reference_number = $generator->next($request->user()->society);
        $unit->save();

        return new UnitResource($unit->fresh(['block', 'street', 'category', 'tariffType']));
    }

    public function update(StoreUnitRequest $request, Unit $unit)
    {
        // reference_number is permanent and never editable, see docs/decisions.md
        $unit->update($request->safe()->except(['reference_number']));

        return new UnitResource($unit->fresh(['block', 'street', 'category', 'tariffType']));
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json(['message' => 'Unit deactivated.']);
    }

    /**
     * Admin action: unlink a resident from a unit (tenant left / ownership
     * changed). Bill history stays with the unit; the new occupant
     * verifies fresh. See docs/decisions.md - Auth / no-OTP verification.
     */
    public function releaseUnit(Unit $unit)
    {
        abort_if($unit->linked_user_id === null, 422, 'Unit is not linked to any account.');

        $old = ['linked_user_id' => $unit->linked_user_id, 'linked_at' => $unit->linked_at];
        $unit->forceFill(['linked_user_id' => null, 'linked_at' => null])->save();

        AuditLog::record('unit.released', $unit, $old, ['linked_user_id' => null]);

        return response()->json(['message' => 'Unit released.', 'unit' => new UnitResource($unit)]);
    }

    public function import(Request $request, ReferenceNumberGenerator $generator)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls']]);

        $import = new UnitsImport($request->user()->society, $generator);
        Excel::import($import, $request->file('file'));

        AuditLog::record('units.bulk_import', null, null, ['created' => $import->created, 'skipped' => $import->skipped]);

        return response()->json([
            'created' => $import->created,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
        ]);
    }
}
