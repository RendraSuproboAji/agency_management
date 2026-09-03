<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);

        $middleware->web(append: [HandleInertiaRequests::class, SecurityHeaders::class]);

        // Di belakang proxy yang menerminasi TLS, tanpa ini Laravel melihat
        // "http" sehingga redirect dan URL absolut keluar sebagai http://
        // dan cookie tidak pernah ditandai Secure.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        // Tamu di area portal diarahkan ke login klien, bukan login staff.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('portal', 'portal/*')
            ? route('portal.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
