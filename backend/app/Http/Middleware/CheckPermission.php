<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: ->middleware('permission:bills.generate')
 * Platform admins bypass (they operate under a separate permission-less
 * guard scoped to platform-only routes); society_staff users must hold
 * the permission via one of their roles.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isSocietyStaff() || ! $user->hasPermission($permission)) {
            abort(403, "Missing permission: {$permission}");
        }

        return $next($request);
    }
}
