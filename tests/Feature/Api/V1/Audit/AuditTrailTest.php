<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function auditTrailApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowAuditTrailApi(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

it('protects every audit trail with authentication and explicit permissions', function (): void {
    $this->getJson('/api/v1/audit/domain-events')->assertUnauthorized();

    Sanctum::actingAs(auditTrailApiUser());

    $this->getJson('/api/v1/audit/domain-events')
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
    $this->getJson('/api/v1/audit/activities')->assertForbidden();
    $this->getJson('/api/v1/audit/security-events')->assertForbidden();
});

it('filters and paginates domain audit events newest first', function (): void {
    allowAuditTrailApi();
    Sanctum::actingAs(auditTrailApiUser());
    $aggregateId = Uuid::generate()->value();
    $correlationId = Uuid::generate()->value();
    $olderId = Uuid::generate()->value();
    $newerId = Uuid::generate()->value();

    DB::table('audit_log')->insert([
        [
            'event_id' => $olderId,
            'event_name' => 'account.opened',
            'aggregate_type' => 'account',
            'aggregate_id' => $aggregateId,
            'aggregate_version' => 1,
            'correlation_id' => $correlationId,
            'payload' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
            'occurred_on' => '2026-10-01 09:00:00',
            'recorded_at' => '2026-10-01 09:00:01',
        ],
        [
            'event_id' => $newerId,
            'event_name' => 'account.activated',
            'aggregate_type' => 'account',
            'aggregate_id' => $aggregateId,
            'aggregate_version' => 2,
            'correlation_id' => $correlationId,
            'payload' => json_encode(['status' => 'active'], JSON_THROW_ON_ERROR),
            'occurred_on' => '2026-10-02 10:00:00',
            'recorded_at' => '2026-10-02 10:00:01',
        ],
    ]);

    $this->getJson("/api/v1/audit/domain-events?aggregate_id={$aggregateId}&correlation_id={$correlationId}&from=2026-10-01&to=2026-10-02&per_page=1")
        ->assertOk()
        ->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.event_id', $newerId)
        ->assertJsonPath('data.events.0.payload.status', 'active')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2);
});

it('filters authenticated activity by actor action and response status', function (): void {
    allowAuditTrailApi();
    Sanctum::actingAs(auditTrailApiUser());
    $actorId = Uuid::generate()->value();
    DB::table('activity_log')->insert([
        'id' => Uuid::generate()->value(),
        'actor_type' => User::class,
        'actor_id' => $actorId,
        'action' => 'api.v1.accounts.store',
        'http_method' => 'POST',
        'response_status' => 201,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Audit test client',
        'metadata' => json_encode(['route' => 'api/v1/accounts'], JSON_THROW_ON_ERROR),
        'occurred_on' => '2026-10-03 12:00:00',
    ]);

    $this->getJson("/api/v1/audit/activities?actor_id={$actorId}&action=api.v1.accounts.store&response_status=201")
        ->assertOk()
        ->assertJsonCount(1, 'data.activities')
        ->assertJsonPath('data.activities.0.http_method', 'POST')
        ->assertJsonPath('data.activities.0.metadata.route', 'api/v1/accounts');
});

it('filters security events by controlled type severity and subject', function (): void {
    allowAuditTrailApi();
    Sanctum::actingAs(auditTrailApiUser());
    $subjectId = Uuid::generate()->value();
    DB::table('security_events')->insert([
        'id' => Uuid::generate()->value(),
        'type' => 'identity.access_denied',
        'severity' => 'warning',
        'subject_id' => $subjectId,
        'subject_fingerprint' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Security test client',
        'details' => json_encode(['permission' => 'audit.view'], JSON_THROW_ON_ERROR),
        'occurred_on' => '2026-10-04 08:00:00',
    ]);

    $this->getJson("/api/v1/audit/security-events?type=identity.access_denied&severity=warning&subject_id={$subjectId}")
        ->assertOk()
        ->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.type', 'identity.access_denied')
        ->assertJsonPath('data.events.0.details.permission', 'audit.view');
});

it('rejects unsafe pagination dates and unknown security filters', function (): void {
    allowAuditTrailApi();
    Sanctum::actingAs(auditTrailApiUser());

    $this->getJson('/api/v1/audit/security-events?from=2026-10-04&to=2026-10-01&page=0&per_page=101&type=unknown&severity=emergency')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'page', 'per_page', 'type', 'severity']);
});
