<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('blocks repeated login attempts with a safe JSON response', function (): void {
    config()->set('security.rate_limits.login_per_minute', 2);

    $credentials = [
        'email' => 'rate-limited@example.com',
        'password' => 'incorrect-password',
        'device_name' => 'Security Test',
    ];

    $this->postJson('/api/v1/auth/login', $credentials)->assertUnauthorized();
    $this->postJson('/api/v1/auth/login', $credentials)->assertUnauthorized();

    $this->postJson('/api/v1/auth/login', $credentials)
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJson([
            'message' => 'Too many requests. Please wait before trying again.',
        ]);
});

it('limits one client ip address even when it changes the attempted email', function (): void {
    config()->set('security.rate_limits.login_per_minute', 100);
    config()->set('security.rate_limits.login_ip_per_minute', 2);

    foreach (['first@example.com', 'second@example.com'] as $email) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'incorrect-password',
            'device_name' => 'Security Test',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'third@example.com',
        'password' => 'incorrect-password',
        'device_name' => 'Security Test',
    ])->assertStatus(429);
});

it('gives each authenticated user an independent money-movement budget', function (): void {
    config()->set('security.rate_limits.money_movement_per_minute', 1);

    Route::middleware(['auth:sanctum', 'throttle:money-movement'])
        ->get('/api/testing/money-movement-limit', static fn() => response()->json(['allowed' => true]));

    $firstUser = User::factory()->create();
    Sanctum::actingAs($firstUser);

    $this->getJson('/api/testing/money-movement-limit')->assertOk();
    $this->getJson('/api/testing/money-movement-limit')
        ->assertStatus(429)
        ->assertJsonPath('message', 'Too many requests. Please wait before trying again.');

    $secondUser = User::factory()->create();
    Sanctum::actingAs($secondUser);

    $this->getJson('/api/testing/money-movement-limit')->assertOk();
});

it('assigns named protection policies to sensitive API areas', function (): void {
    expect(Route::getRoutes()->getByName('api.v1.auth.login')?->gatherMiddleware())
        ->toContain('throttle:login')
        ->and(Route::getRoutes()->getByName('api.v1.accounts.transfers.store')?->gatherMiddleware())
        ->toContain('throttle:money-movement')
        ->and(Route::getRoutes()->getByName('api.v1.operations.deposits.store')?->gatherMiddleware())
        ->toContain('throttle:operator-api', 'throttle:money-movement')
        ->and(Route::getRoutes()->getByName('api.v1.reports.ledger.show')?->gatherMiddleware())
        ->toContain('throttle:reporting-api')
        ->and(Route::getRoutes()->getByName('api.v1.administration.staff.index')?->gatherMiddleware())
        ->toContain('throttle:operator-api');
});

it('does not expose an API route without a request limit', function (): void {
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }

        $hasRequestLimit = collect($route->gatherMiddleware())
            ->contains(static fn(string $middleware): bool => str_starts_with($middleware, 'throttle:'));

        expect($hasRequestLimit)->toBeTrue("Route [{$route->uri()}] must have a named request limit.");
    }
});
