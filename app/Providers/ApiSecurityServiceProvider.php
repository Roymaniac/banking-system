<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Registers named request limits for public and authenticated API traffic. */
final class ApiSecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request): array => [
            $this->limit('login_per_minute', $this->loginKey($request)),
            $this->limit('login_ip_per_minute', 'login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('customer-api', fn (Request $request): Limit => $this->limit(
            'customer_per_minute',
            $this->authenticatedKey($request),
        ));

        RateLimiter::for('money-movement', fn (Request $request): Limit => $this->limit(
            'money_movement_per_minute',
            $this->authenticatedKey($request),
        ));

        RateLimiter::for('operator-api', fn (Request $request): Limit => $this->limit(
            'operator_per_minute',
            $this->authenticatedKey($request),
        ));

        RateLimiter::for('reporting-api', fn (Request $request): Limit => $this->limit(
            'reporting_per_minute',
            $this->authenticatedKey($request),
        ));
    }

    private function limit(string $configurationKey, string $requestKey): Limit
    {
        $attempts = max(1, (int) config("security.rate_limits.{$configurationKey}"));

        return Limit::perMinute($attempts)
            ->by($requestKey)
            ->response(static fn (Request $request, array $headers): JsonResponse => response()->json(
                ['message' => 'Too many requests. Please wait before trying again.'],
                429,
                $headers,
            ));
    }

    private function loginKey(Request $request): string
    {
        $email = mb_strtolower(trim((string) $request->input('email')));

        // Hash the email so credentials and personal information never become
        // visible in cache keys or infrastructure diagnostics.
        return 'login:'.hash('sha256', $email).'|ip:'.$request->ip();
    }

    private function authenticatedKey(Request $request): string
    {
        $identifier = $request->user()?->getAuthIdentifier();

        return $identifier !== null
            ? 'user:'.(string) $identifier
            : 'ip:'.$request->ip();
    }
}
