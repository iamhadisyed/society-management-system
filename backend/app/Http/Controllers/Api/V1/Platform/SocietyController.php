<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSocietyRequest;
use App\Models\PlatformAuditLog;
use App\Models\Society;
use App\Models\User;
use App\Services\RoleProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocietyController extends Controller
{
    public function index(Request $request)
    {
        $societies = Society::query()
            ->withCount('users')
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('code', 'like', "%{$v}%")))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($societies);
    }

    public function show(Society $society)
    {
        return response()->json($society->loadCount('users'));
    }

    public function store(StoreSocietyRequest $request)
    {
        $data = $request->validated();

        $society = DB::transaction(function () use ($data, $request) {
            $society = Society::create([
                ...collect($data)->except(['admin_name', 'admin_email', 'admin_password'])->all(),
                'status' => 'active',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            (new RoleProvisioningService)->provision($society);

            $adminRole = $society->roles()->where('slug', 'society-administrator')->first();

            $admin = User::create([
                'society_id' => $society->id,
                'user_type' => 'society_staff',
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ]);
            $admin->roles()->attach($adminRole->id);

            return $society;
        });

        PlatformAuditLog::record('society.created', $society, null, $society->toArray());

        return response()->json($society, 201);
    }

    public function update(Request $request, Society $society)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_iban' => ['nullable', 'string', 'max:100'],
            'settings' => ['nullable', 'array'],
        ]);

        $old = $society->only(array_keys($data));
        $society->update($data + ['updated_by' => $request->user()->id]);

        PlatformAuditLog::record('society.updated', $society, $old, $data);

        return response()->json($society);
    }

    public function suspend(Request $request, Society $society)
    {
        $society->update(['status' => 'suspended', 'updated_by' => $request->user()->id]);
        PlatformAuditLog::record('society.suspended', $society);

        return response()->json($society);
    }

    public function activate(Request $request, Society $society)
    {
        $society->update(['status' => 'active', 'updated_by' => $request->user()->id]);
        PlatformAuditLog::record('society.activated', $society);

        return response()->json($society);
    }
}
