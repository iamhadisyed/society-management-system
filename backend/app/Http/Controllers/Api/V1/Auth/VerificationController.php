<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingVerification;
use App\Models\Society;
use App\Models\Unit;
use App\Models\UnitClaimDispute;
use App\Models\User;
use App\Models\VerificationAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * No-OTP resident registration/verification flow (docs/decisions.md).
 * One unit = one account. Name + latest bill number must both match
 * the unit record; a mismatch never hard-rejects, it queues for
 * manual Society Admin approval. An already-linked unit never gets
 * silently taken over - it raises a claim dispute instead.
 */
class VerificationController extends Controller
{
    public function societies(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $societies = Society::where('status', 'active')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'city', 'logo_path']);

        return response()->json($societies);
    }

    public function units(Request $request, Society $society)
    {
        $data = $request->validate([
            'block_id' => ['nullable', 'integer'],
            'street_id' => ['nullable', 'integer'],
        ]);

        $units = Unit::withoutGlobalScopes()
            ->where('society_id', $society->id)
            ->where('is_active', true)
            ->when($data['block_id'] ?? null, fn ($q, $v) => $q->where('block_id', $v))
            ->when($data['street_id'] ?? null, fn ($q, $v) => $q->where('street_id', $v))
            ->orderBy('unit_number')
            ->get(['id', 'block_id', 'street_id', 'unit_number']);

        return response()->json($units);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'society_code' => ['required', 'string'],
            'unit_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'bill_number' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_id' => ['nullable', 'string'],
        ]);

        $society = Society::where('code', $data['society_code'])->where('status', 'active')->firstOrFail();
        $unit = Unit::withoutGlobalScopes()->where('society_id', $society->id)->findOrFail($data['unit_id']);

        $this->assertNotLockedOut($society, $unit, $data['device_id'] ?? null, $request->ip());

        $matched = $this->namesMatch($data['name'], $unit) && $this->billNumberMatches($data['bill_number'], $unit);

        VerificationAttempt::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'device_id' => $data['device_id'] ?? null,
            'ip_address' => $request->ip(),
            'submitted_name' => $data['name'],
            'submitted_bill_number' => $data['bill_number'],
            'matched' => $matched,
        ]);

        if (! $matched) {
            PendingVerification::create([
                'society_id' => $society->id,
                'unit_id' => $unit->id,
                'submitted_name' => $data['name'],
                'submitted_bill_number' => $data['bill_number'],
                'password_hash' => Hash::make($data['password']),
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => 'pending_manual_approval',
                'message' => 'Details did not auto-match. Sent to the Society Administrator for manual approval.',
            ], 202);
        }

        if ($unit->linked_user_id !== null) {
            UnitClaimDispute::create([
                'society_id' => $society->id,
                'unit_id' => $unit->id,
                'existing_user_id' => $unit->linked_user_id,
                'claimant_name' => $data['name'],
                'claimant_bill_number' => $data['bill_number'],
                'claimant_password_hash' => Hash::make($data['password']),
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => 'claim_dispute',
                'message' => 'This unit is already linked to an account. A claim dispute has been raised for the Society Administrator to resolve.',
            ], 409);
        }

        $user = User::create([
            'society_id' => $society->id,
            'user_type' => 'resident',
            'name' => $data['name'],
            'password' => $data['password'],
            'language' => $society->default_language ?? 'en',
        ]);

        $unit->forceFill(['linked_user_id' => $user->id, 'linked_at' => now()])->save();

        $token = $user->createToken($request->userAgent() ?? 'api')->plainTextToken;

        return response()->json(['status' => 'verified', 'token' => $token, 'user' => $user]);
    }

    /**
     * Unit switcher: an already-authenticated resident links another
     * unit to the same login via the same match rules.
     */
    public function linkAdditionalUnit(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isResident(), 403);

        $data = $request->validate([
            'society_code' => ['required', 'string'],
            'unit_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'bill_number' => ['required', 'string', 'max:100'],
        ]);

        $society = Society::where('code', $data['society_code'])->where('status', 'active')->firstOrFail();
        $unit = Unit::withoutGlobalScopes()->where('society_id', $society->id)->findOrFail($data['unit_id']);

        $this->assertNotLockedOut($society, $unit, null, $request->ip());

        $matched = $this->namesMatch($data['name'], $unit) && $this->billNumberMatches($data['bill_number'], $unit);

        VerificationAttempt::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'ip_address' => $request->ip(),
            'submitted_name' => $data['name'],
            'submitted_bill_number' => $data['bill_number'],
            'matched' => $matched,
        ]);

        if (! $matched) {
            throw ValidationException::withMessages(['bill_number' => ['Name / latest bill number did not match this unit.']]);
        }

        if ($unit->linked_user_id !== null) {
            if ($unit->linked_user_id === $user->id) {
                return response()->json(['status' => 'already_linked']);
            }

            UnitClaimDispute::create([
                'society_id' => $society->id,
                'unit_id' => $unit->id,
                'existing_user_id' => $unit->linked_user_id,
                'claimant_name' => $data['name'],
                'claimant_bill_number' => $data['bill_number'],
                'claimant_password_hash' => $user->password,
                'status' => 'pending',
            ]);

            return response()->json(['status' => 'claim_dispute'], 409);
        }

        $unit->forceFill(['linked_user_id' => $user->id, 'linked_at' => now()])->save();

        return response()->json(['status' => 'linked', 'unit' => $unit]);
    }

    private function namesMatch(string $submitted, Unit $unit): bool
    {
        $normalize = fn (?string $s) => Str::of((string) $s)->lower()->squish()->toString();
        $submitted = $normalize($submitted);

        return $submitted !== '' && (
            $submitted === $normalize($unit->owner_name)
            || $submitted === $normalize($unit->occupant_name)
        );
    }

    private function billNumberMatches(string $submitted, Unit $unit): bool
    {
        // Only the LATEST bill number is ever valid - it's overwritten every bill run.
        return $unit->current_bill_number !== null
            && trim($submitted) === trim($unit->current_bill_number);
    }

    private function assertNotLockedOut(Society $society, Unit $unit, ?string $deviceId, string $ip): void
    {
        $maxAttempts = (int) ($society->setting('verification_lockout_attempts') ?? 5);
        $cooldownMinutes = (int) ($society->setting('verification_cooldown_minutes') ?? 30);

        $since = now()->subMinutes($cooldownMinutes);

        $recentFailed = VerificationAttempt::withoutGlobalScopes()
            ->where('society_id', $society->id)
            ->where('unit_id', $unit->id)
            ->where('matched', false)
            ->where('created_at', '>=', $since)
            ->where(function ($q) use ($deviceId, $ip) {
                $q->where('ip_address', $ip);
                if ($deviceId) {
                    $q->orWhere('device_id', $deviceId);
                }
            })
            ->count();

        if ($recentFailed >= $maxAttempts) {
            throw ValidationException::withMessages([
                'bill_number' => ["Too many failed attempts. Try again in {$cooldownMinutes} minutes."],
            ]);
        }
    }
}
