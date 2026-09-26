<?php

declare(strict_types=1);

use App\Models\User;
use Audit\Domain\Activity\Repository\ActivityLogRepository;
use Audit\Infrastructure\Persistence\DatabaseActivityLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Route::post('/activity-test', fn() => response()->noContent())
        ->name('activity.test');
    Route::get('/activity-read-test', fn() => response()->noContent())
        ->name('activity.read-test');
});

it('binds activity storage to its database adapter', function (): void {
    expect(app(ActivityLogRepository::class))->toBeInstanceOf(DatabaseActivityLogRepository::class);
});

it('records an authenticated state-changing request without storing its body', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Banking Test Client')
        ->postJson(
            '/activity-test',
            ['password' => 'must-not-be-recorded']
        )
        ->assertNoContent();

    $record = DB::table('activity_log')->first();

    expect($record)->not->toBeNull()
        ->and($record->actor_type)->toBe(User::class)
        ->and($record->actor_id)->toBe((string) $user->getKey())
        ->and($record->action)->toBe('activity.test')
        ->and($record->http_method)->toBe('POST')
        ->and($record->response_status)->toBe(204)
        ->and($record->user_agent)->toBe('Banking Test Client')
        ->and($record->metadata)->not->toContain('must-not-be-recorded');
});

it('does not record read-only or unauthenticated requests', function (): void {
    $this->get('/activity-read-test')->assertNoContent();
    $this->postJson('/activity-test')->assertNoContent();

    expect(DB::table('activity_log')->count())->toBe(0);
});
