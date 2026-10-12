<?php

namespace App\Support\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the "current society" for the SocietyScope global scope and
 * for anywhere else tenant context is needed (services, jobs, PDF
 * generation). HTTP requests get it for free from the authenticated
 * user; console/queue contexts (bill run jobs, scheduler commands) must
 * set it explicitly with Tenant::run() since there is no authenticated
 * request to read it from.
 */
class Tenant
{
    private static ?int $override = null;

    public static function id(): ?int
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $user = Auth::guard('sanctum')->user();

        if ($user instanceof User) {
            return $user->society_id;
        }

        return null;
    }

    /**
     * Run a callback with an explicit society context (for queued jobs,
     * artisan commands, and the scheduler - none of which have an
     * authenticated HTTP user to infer society_id from).
     */
    public static function run(int $societyId, \Closure $callback): mixed
    {
        $previous = self::$override;
        self::$override = $societyId;

        try {
            return $callback();
        } finally {
            self::$override = $previous;
        }
    }
}
