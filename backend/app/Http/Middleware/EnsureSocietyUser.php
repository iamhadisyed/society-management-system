<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** For all tenant (society-scoped) API routes: rejects platform admin tokens and inactive/suspended societies. */
class EnsureSocietyUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403, 'Society account access only.');
        abort_if(! $user->is_active, 403, 'Account is inactive.');
        abort_if($user->society?->status === 'suspended', 403, 'Society is suspended.');

        return $next($request);
    }
}
