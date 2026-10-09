<?php

use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\TraceApiRequest;
use Audit\Infrastructure\Http\Middleware\RecordUserActivityMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Transaction\Application\Control\Exception\InvalidMoneyMovementResume;
use Transaction\Application\Control\Exception\MoneyMovementSuspended;

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
        $exceptions->render(
            fn (MoneyMovementSuspended $exception) => response()->json([
                'message' => $exception->getMessage(),
            ], 503)
        );
        $exceptions->render(
            fn (InvalidMoneyMovementResume $exception) => response()->json([
                'message' => $exception->getMessage(),
            ], 409)
        );
    })->create();
