<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Shared\Domain\Identifier\Uuid;
use Shared\Infrastructure\Health\RecordSchedulerHeartbeat;

uses(RefreshDatabase::class);

function systemHealthApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowSystemHealthApi(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

it('keeps liveness public but protects detailed readiness information', function (): void {
    $this->get('/up')->assertOk()->assertDontSee('pending_jobs');
    $this->getJson('/api/v1/operations/health')->assertUnauthorized();

    Sanctum::actingAs(systemHealthApiUser());

    $this->getJson('/api/v1/operations/health')
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
});

it('reports healthy when critical services are current', function (): void {
    allowSystemHealthApi();
    Sanctum::actingAs(systemHealthApiUser());
    config()->set('queue.default', 'database');
    app(RecordSchedulerHeartbeat::class)->record();

    $this->getJson('/api/v1/operations/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'healthy')
        ->assertJsonPath('data.components.database.status', 'healthy')
        ->assertJsonPath('data.components.scheduler.status', 'healthy')
        ->assertJsonPath('data.components.queue.status', 'healthy')
        ->assertJsonPath('data.components.queue.driver', 'database')
        ->assertJsonPath('data.components.notifications.status', 'healthy');
});

it('reports degraded without going offline for operational backlogs', function (): void {
    allowSystemHealthApi();
    Sanctum::actingAs(systemHealthApiUser());
    config()->set('queue.default', 'database');
    app(RecordSchedulerHeartbeat::class)->record();
    DB::table('failed_jobs')->insert([
        'uuid' => Uuid::generate()->value(),
        'connection' => 'database',
        'queue' => 'notifications',
        'payload' => '{}',
        'exception' => 'Safe test failure',
        'failed_at' => now(),
    ]);
    DB::table('email_outbox')->insert([
        'id' => Uuid::generate()->value(),
        'encrypted_payload' => 'encrypted',
        'attempts' => 5,
        'recorded_at' => now(),
        'last_attempted_at' => now(),
        'delivered_at' => null,
    ]);

    $this->getJson('/api/v1/operations/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.components.queue.failed_jobs', 1)
        ->assertJsonPath('data.components.notifications.exhausted_messages', 1);
});

it('returns service unavailable when the scheduler heartbeat is stale', function (): void {
    allowSystemHealthApi();
    Sanctum::actingAs(systemHealthApiUser());
    config()->set('queue.default', 'database');
    DB::table('system_heartbeats')->insert([
        'name' => 'scheduler',
        'recorded_at' => now()->subMinutes(5),
    ]);

    $this->getJson('/api/v1/operations/health')
        ->assertServiceUnavailable()
        ->assertJsonPath('data.status', 'unhealthy')
        ->assertJsonPath('data.components.scheduler.status', 'unhealthy');
});

it('records scheduler freshness without creating duplicate heartbeat rows', function (): void {
    $heartbeat = app(RecordSchedulerHeartbeat::class);

    $heartbeat->record();
    $heartbeat->record();

    $this->assertDatabaseCount('system_heartbeats', 1);
    $this->assertDatabaseHas('system_heartbeats', ['name' => 'scheduler']);
});

it('registers the scheduler heartbeat to run every minute', function (): void {
    $descriptions = collect(app(Schedule::class)->events())
        ->pluck('description');

    expect($descriptions)->toContain('scheduler-heartbeat');
});
