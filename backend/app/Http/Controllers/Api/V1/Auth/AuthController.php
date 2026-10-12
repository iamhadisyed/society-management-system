<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\Society;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Society staff login: email + password. */
    public function staffLogin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])
            ->where('user_type', 'society_staff')
            ->first();

        $this->assertCredentials($user, $data['password']);

        return $this->respondWithToken($user, $request);
    }

    /**
     * Resident login: society code + unit reference number + password.
     * Society code is required because reference_number is only unique
     * per society (see docs/decisions.md).
     */
    public function residentLogin(Request $request)
    {
        $data = $request->validate([
            'society_code' => ['required', 'string'],
            'reference_number' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $society = Society::where('code', $data['society_code'])->first();
        $unit = $society
            ? Unit::withoutGlobalScopes()->where('society_id', $society->id)
                ->where('reference_number', $data['reference_number'])->first()
            : null;

        $user = $unit?->linked_user_id
            ? User::where('id', $unit->linked_user_id)->where('user_type', 'resident')->first()
            : null;

        $this->assertCredentials($user, $data['password']);

        return $this->respondWithToken($user, $request);
    }

    /** Platform Administrator login (separate table/model, not society-scoped). */
    public function platformLogin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = PlatformAdmin::where('email', $data['email'])->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password) || ! $admin->is_active) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        $admin->forceFill(['last_login_at' => now()])->save();
        $token = $admin->createToken($request->userAgent() ?? 'api')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $admin]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->load('roles.permissions', 'units:id,unit_number,full_address,linked_user_id');

            return response()->json([
                'user' => $user,
                'permissions' => $user->permissionKeys(),
            ]);
        }

        return response()->json(['user' => $user, 'permissions' => []]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function assertCredentials(?User $user, string $password): void
    {
        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => ['Invalid credentials.']]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['password' => ['Account is inactive.']]);
        }
    }

    private function respondWithToken(User $user, Request $request)
    {
        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($request->userAgent() ?? 'api')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }
}
