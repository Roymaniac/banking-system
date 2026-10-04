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

function notificationOperationsUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowNotificationOperations(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

/** @param array<string, mixed> $overrides */
function addOutboxRecord(array $overrides = []): string
{
    $id = Uuid::generate()->value();
    DB::table('email_outbox')->insert([
        'id' => $id,
        'encrypted_payload' => 'encrypted-secret-recipient@example.test-and-private-body',
        'attempts' => 0,
        'recorded_at' => '2026-10-04 09:00:00',
        'last_attempted_at' => null,
        'delivered_at' => null,
        ...$overrides,
    ]);

    return $id;
}

it('requires authentication and notification viewing permission', function (): void {
    $this->getJson('/api/v1/notifications/outbox')->assertUnauthorized();
    $messageId = Uuid::generate()->value();
    $this->postJson("/api/v1/notifications/outbox/{$messageId}/retry")->assertUnauthorized();

    Sanctum::actingAs(notificationOperationsUser());

    $this->getJson('/api/v1/notifications/outbox')
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
    $this->postJson("/api/v1/notifications/outbox/{$messageId}/retry")->assertForbidden();
});

it('reports pending delivered and exhausted outbox health', function (): void {
    allowNotificationOperations();
    Sanctum::actingAs(notificationOperationsUser());
    $pendingId = addOutboxRecord();
    addOutboxRecord([
        'attempts' => 1,
        'last_attempted_at' => '2026-10-04 09:05:00',
        'delivered_at' => '2026-10-04 09:05:00',
    ]);
    addOutboxRecord([
        'attempts' => 5,
        'last_attempted_at' => '2026-10-04 09:10:00',
    ]);

    $this->getJson('/api/v1/notifications/outbox')
        ->assertOk()
        ->assertJsonCount(3, 'data.messages')
        ->assertJsonPath('data.summary.pending', 1)
        ->assertJsonPath('data.summary.delivered', 1)
        ->assertJsonPath('data.summary.exhausted', 1)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonFragment([
            'id' => $pendingId,
            'status' => 'pending',
            'attempts' => 0,
            'maximum_attempts' => 5,
        ])
        ->assertDontSee('recipient@example.test')
        ->assertDontSee('private-body');
});

it('filters outbox records by status attempts and inclusive recorded date', function (): void {
    allowNotificationOperations();
    Sanctum::actingAs(notificationOperationsUser());
    $matchingId = addOutboxRecord([
        'attempts' => 5,
        'recorded_at' => '2026-10-04 23:59:59',
        'last_attempted_at' => '2026-10-04 23:59:59',
    ]);
    addOutboxRecord([
        'attempts' => 4,
        'recorded_at' => '2026-10-04 10:00:00',
    ]);
    addOutboxRecord([
        'attempts' => 5,
        'recorded_at' => '2026-10-03 10:00:00',
    ]);

    $this->getJson('/api/v1/notifications/outbox?status=exhausted&attempts=5&from=2026-10-04&to=2026-10-04&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.id', $matchingId)
        ->assertJsonPath('data.messages.0.status', 'exhausted')
        ->assertJsonPath('meta.total', 1);
});

it('rejects unknown statuses invalid periods and unsafe page sizes', function (): void {
    allowNotificationOperations();
    Sanctum::actingAs(notificationOperationsUser());

    $this->getJson('/api/v1/notifications/outbox?status=failed&from=2026-10-04&to=2026-10-01&page=0&per_page=101&attempts=-1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'to', 'page', 'per_page', 'attempts']);
});

it('requeues an exhausted email and records the operator action', function (): void {
    allowNotificationOperations();
    Sanctum::actingAs(notificationOperationsUser());
    $messageId = addOutboxRecord([
        'attempts' => 5,
        'last_attempted_at' => '2026-10-04 10:00:00',
    ]);

    $this->postJson("/api/v1/notifications/outbox/{$messageId}/retry")
        ->assertAccepted()
        ->assertJsonPath('message', 'Email requeued for delivery successfully.');

    $this->assertDatabaseHas('email_outbox', [
        'id' => $messageId,
        'attempts' => 0,
        'retry_cycles' => 1,
        'last_attempted_at' => null,
        'delivered_at' => null,
    ]);
    expect(DB::table('email_outbox')->where('id', $messageId)->value('requeued_at'))
        ->not->toBeNull();
    $this->assertDatabaseHas('activity_log', [
        'action' => 'api.v1.notifications.outbox.retry',
        'http_method' => 'POST',
        'response_status' => 202,
    ]);

    $this->getJson('/api/v1/notifications/outbox?status=pending')
        ->assertOk()
        ->assertJsonPath('data.messages.0.id', $messageId)
        ->assertJsonPath('data.messages.0.retry_cycles', 1);
});

it('refuses retry for pending delivered and missing outbox messages', function (): void {
    allowNotificationOperations();
    Sanctum::actingAs(notificationOperationsUser());
    $pendingId = addOutboxRecord(['attempts' => 4]);
    $deliveredId = addOutboxRecord([
        'attempts' => 5,
        'delivered_at' => '2026-10-04 10:00:00',
    ]);
    $missingId = Uuid::generate()->value();

    $this->postJson("/api/v1/notifications/outbox/{$pendingId}/retry")
        ->assertConflict()
        ->assertJsonPath('message', 'This email is still eligible for automatic delivery and does not need a manual retry.');
    $this->postJson("/api/v1/notifications/outbox/{$deliveredId}/retry")
        ->assertConflict()
        ->assertJsonPath('message', 'A delivered email cannot be retried.');
    $this->postJson("/api/v1/notifications/outbox/{$missingId}/retry")
        ->assertNotFound();
});
