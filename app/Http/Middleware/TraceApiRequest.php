<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Adds a safe request identifier and records one structured completion log. */
final class TraceApiRequest
{
    public const HEADER = 'X-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $startedAt = hrtime(true);

        // Sharing this context means framework and application logs written
        // during the request can be found using the same identifier.
        Log::withContext(['request_id' => $requestId]);
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        if ($this->shouldLog($request)) {
            Log::info('HTTP request completed.', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'route' => $request->route()?->getName() ?? 'unnamed',
                'status' => $response->getStatusCode(),
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
                'authenticated' => $request->user() !== null,
            ]);
        }

        return $response;
    }

    private function requestId(Request $request): string
    {
        $supplied = $request->headers->get(self::HEADER);

        return is_string($supplied) && Str::isUuid($supplied)
            ? strtolower($supplied)
            : (string) Str::uuid();
    }

    private function shouldLog(Request $request): bool
    {
        if (! (bool) config('observability.request_logging.enabled', true)) {
            return false;
        }

        return ! ((bool) config('observability.request_logging.exclude_liveness', true)
            && $request->is('up'));
    }
}
