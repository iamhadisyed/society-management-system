<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // API-only backend: no `login` named route exists, so override the
        // framework's default guest-redirect (which calls route('login')
        // and throws RouteNotFoundException) - unauthenticated requests
        // should just get a 401, handled below via withExceptions().
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'platform_admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'society_user' => \App\Http\Middleware\EnsureSocietyUser::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This is an API-only backend - every route lives under /api, so an
        // unauthenticated request should always get 401 JSON, never a
        // redirect to a `login` named route that doesn't exist here
        // (the default Authenticate middleware only skips that redirect
        // when the request's Accept header already says JSON).
        $exceptions->render(fn (\Illuminate\Auth\AuthenticationException $e) => response()->json(['message' => 'Unauthenticated.'], 401));
    })->create();
