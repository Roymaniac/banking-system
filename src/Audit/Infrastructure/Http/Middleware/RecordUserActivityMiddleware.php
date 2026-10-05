<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Http\Middleware;

use Audit\Application\Activity\RecordUserActivity;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Records successful state-changing requests made by signed-in users. */
final readonly class RecordUserActivityMiddleware
{
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private RecordUserActivity $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $actor = $request->user();

        if (! $actor instanceof Authenticatable
            || ! in_array($request->method(), self::STATE_CHANGING_METHODS, true)
            || $response->getStatusCode() >= 500) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route->getName();
        $routeTemplate = $route->uri();

        $this->recorder->record(
            $actor::class,
            (string) $actor->getAuthIdentifier(),
            $routeName ?? sprintf('%s %s', $request->method(), $routeTemplate),
            $request->method(),
            $response->getStatusCode(),
            $request->ip(),
            $request->userAgent(),
            ['route' => $routeTemplate],
        );

        return $response;
    }
}
