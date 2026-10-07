<?php

use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\TraceApiRequest;
use Audit\Infrastructure\Http\Middleware\RecordUserActivityMiddleware;
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
        // Give every response and application log a shared trace identifier.
        $middleware->prepend(TraceApiRequest::class);

        // Capture authenticated changes from both web and API requests.
        $middleware->append(RecordUserActivityMiddleware::class);
        $middleware->alias([
            'permission' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
