<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function moneyMovementOperator(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowMoneyMovementControl(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

it('requires authentication and explicit permissions', function (): void {
    $this->getJson('/api/v1/operations/money-movement')->assertUnauthorized();
    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Unauthorized incident control attempt.',
    ])->assertUnauthorized();

    Sanctum::actingAs(moneyMovementOperator());

    $this->getJson('/api/v1/operations/money-movement')->assertForbidden();
    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Unauthorized incident control attempt.',
    ])->assertForbidden();
});

it('shows the protected current state to an authorized operator', function (): void {
    allowMoneyMovementControl();
    Sanctum::actingAs(moneyMovementOperator());

    $this->getJson('/api/v1/operations/money-movement')
        ->assertOk()
        ->assertJsonPath('data.money_movement.enabled', true)
        ->assertJsonPath('data.money_movement.reason', null)
        ->assertJsonPath('data.money_movement.source', 'migration');
});

it('suspends and resumes with the authenticated operator in the audit trail', function (): void {
    allowMoneyMovementControl();
    $operator = moneyMovementOperator();
    Sanctum::actingAs($operator);
    $suspensionReason = 'Investigating reconciliation incident INC-2048.';

    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => $suspensionReason,
    ])->assertOk()
        ->assertJsonPath('data.money_movement.enabled', false)
        ->assertJsonPath('data.money_movement.reason', $suspensionReason)
        ->assertJsonPath('data.money_movement.source', 'operator_api');

    $this->assertDatabaseHas('money_movement_control_events', [
        'action' => 'suspended',
        'reason' => $suspensionReason,
        'source' => 'operator_api',
        'actor_user_id' => $operator->identity_user_id,
    ]);

    $resumeReason = 'Incident INC-2048 resolved and independently approved.';
    $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => $resumeReason,
    ])->assertOk()
        ->assertJsonPath('data.money_movement.enabled', true)
        ->assertJsonPath('data.money_movement.reason', null);

    $this->assertDatabaseHas('money_movement_control_events', [
        'action' => 'resumed',
        'reason' => $resumeReason,
        'source' => 'operator_api',
        'actor_user_id' => $operator->identity_user_id,
    ]);
});

it('validates the incident reason before changing state', function (): void {
    allowMoneyMovementControl();
    Sanctum::actingAs(moneyMovementOperator());

    $this->postJson('/api/v1/operations/money-movement/suspend', ['reason' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => true]);
    $this->assertDatabaseCount('money_movement_control_events', 0);
});
