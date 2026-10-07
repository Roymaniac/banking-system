<?php

declare(strict_types=1);

use App\Http\Middleware\TraceApiRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Formatter\JsonFormatter;

it('returns a valid client request identifier unchanged', function (): void {
    $requestId = (string) Str::uuid();

    $this->withHeader(TraceApiRequest::HEADER, $requestId)
        ->get('/up')
        ->assertOk()
        ->assertHeader(TraceApiRequest::HEADER, $requestId);
});

it('replaces an unsafe request identifier with a generated UUID', function (): void {
    $response = $this->withHeader(TraceApiRequest::HEADER, 'not-a-safe-request-id')
        ->get('/up')
        ->assertOk();

    $requestId = $response->headers->get(TraceApiRequest::HEADER);

    expect($requestId)->toBeString()
        ->and(Str::isUuid($requestId))->toBeTrue()
        ->and($requestId)->not->toBe('not-a-safe-request-id');
});

it('logs safe request metadata without paths parameters or query values', function (): void {
    Route::get('/testing/request-trace/{privateValue}', static fn () => response()->json(['ok' => true]))
        ->name('testing.request-trace');
    Log::spy();

    $this->getJson('/testing/request-trace/private-account-value?token=private-token')
        ->assertOk()
        ->assertHeader(TraceApiRequest::HEADER);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('HTTP request completed.', Mockery::on(function (array $context): bool {
            $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

            return $context['method'] === 'GET'
                && $context['route'] === 'testing.request-trace'
                && $context['status'] === 200
                && $context['authenticated'] === false
                && is_float($context['duration_ms'])
                && Str::isUuid($context['request_id'])
                && ! str_contains($encodedContext, 'private-account-value')
                && ! str_contains($encodedContext, 'private-token');
        }));
});

it('uses JSON formatting for container logs', function (): void {
    expect(config('logging.channels.stderr.formatter'))->toBe(JsonFormatter::class);
});
