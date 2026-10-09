<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Notification\Domain\Outbox\Repository\EmailOutboxRepository;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Control\MoneyMovementControl;
use Transaction\Application\Control\MoneyMovementResumeApproval;
use Transaction\Application\Control\MoneyMovementResumeNotifier;
use Transaction\Application\Control\MoneyMovementResumeRequest;
use Transaction\Application\Control\MoneyMovementResumeRequestExpiry;

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

/** @param list<array{user: User, staff_status: string}> $reviewers */
function configureMoneyMovementApprovers(array $reviewers): void
{
    $departmentId = Uuid::generate()->value();
    $roleId = Uuid::generate()->value();
    $permissionId = Uuid::generate()->value();
    DB::table('departments')->insert([
        'id' => $departmentId,
        'code' => 'OPS',
        'name' => 'Operations',
        'status' => 'active',
        'created_at' => now(),
        'deactivated_at' => null,
        'version' => 1,
    ]);
    DB::table('administration_roles')->insert([
        'id' => $roleId,
        'name' => 'money-movement-approver',
        'label' => 'Money Movement Approver',
        'status' => 'active',
        'created_at' => now(),
        'deactivated_at' => null,
        'version' => 1,
    ]);
    DB::table('administration_permissions')->insert([
        'id' => $permissionId,
        'name' => 'money_movement.approve',
        'label' => 'Approve money movement resumption',
        'created_at' => now(),
        'version' => 1,
    ]);
    DB::table('role_permission_assignments')->insert([
        'role_id' => $roleId,
        'permission_id' => $permissionId,
        'granted_at' => now(),
    ]);

    foreach ($reviewers as $index => $reviewer) {
        $staffId = Uuid::generate()->value();
        DB::table('staff')->insert([
            'id' => $staffId,
            'user_id' => $reviewer['user']->identity_user_id,
            'employee_number' => 'OPS-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'department_id' => $departmentId,
            'job_title' => 'Incident Reviewer',
            'status' => $reviewer['staff_status'],
            'hired_at' => now(),
            'deactivated_at' => $reviewer['staff_status'] === 'active' ? null : now(),
            'version' => 1,
        ]);
        DB::table('staff_role_assignments')->insert([
            'role_id' => $roleId,
            'staff_id' => $staffId,
            'assigned_at' => now(),
        ]);
    }
}

it('requires authentication and explicit permissions', function (): void {
    $this->getJson('/api/v1/operations/money-movement')->assertUnauthorized();
    $this->getJson('/api/v1/operations/money-movement/events')->assertUnauthorized();
    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Unauthorized incident control attempt.',
    ])->assertUnauthorized();
    $this->postJson('/api/v1/operations/money-movement/resume-requests/'.Uuid::generate()->value().'/approve')
        ->assertUnauthorized();
    $this->getJson('/api/v1/operations/money-movement/resume-requests')->assertUnauthorized();

    Sanctum::actingAs(moneyMovementOperator());

    $this->getJson('/api/v1/operations/money-movement')->assertForbidden();
    $this->getJson('/api/v1/operations/money-movement/events')->assertForbidden();
    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Unauthorized incident control attempt.',
    ])->assertForbidden();
    $this->postJson('/api/v1/operations/money-movement/resume-requests/'.Uuid::generate()->value().'/approve')
        ->assertForbidden();
    $this->getJson('/api/v1/operations/money-movement/resume-requests')->assertForbidden();
});

it('shows a bounded approval queue and materializes expired requests', function (): void {
    allowMoneyMovementControl();
    $requester = moneyMovementOperator();
    Sanctum::actingAs($requester);
    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Incident review requires a temporary financial pause.',
    ])->assertOk();
    $requestId = $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => 'All reconciliation checks completed successfully.',
    ])->assertAccepted()->json('data.resume_request.id');

    Sanctum::actingAs(moneyMovementOperator());
    $this->getJson('/api/v1/operations/money-movement/resume-requests?status=pending&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.resume_requests')
        ->assertJsonPath('data.resume_requests.0.id', $requestId)
        ->assertJsonPath('data.resume_requests.0.requested_by', $requester->identity_user_id)
        ->assertJsonPath('meta.total', 1);

    DB::table('money_movement_resume_requests')->where('id', $requestId)->update([
        'expires_at' => now()->subMinute(),
    ]);
    expect(app(MoneyMovementResumeRequestExpiry::class)->expire())->toBe(1);

    $this->getJson('/api/v1/operations/money-movement/resume-requests?status=expired')
        ->assertOk()
        ->assertJsonCount(1, 'data.resume_requests')
        ->assertJsonPath('data.resume_requests.0.status', 'expired');
    $this->getJson('/api/v1/operations/money-movement/resume-requests?status=pending')
        ->assertOk()
        ->assertJsonCount(0, 'data.resume_requests');
});

it('lists bounded control history with incident filters', function (): void {
    allowMoneyMovementControl();
    $requester = moneyMovementOperator();
    Sanctum::actingAs($requester);

    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Investigating incident INC-4096 before settlement.',
    ])->assertOk();
    $resumeRequestId = $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => 'Incident INC-4096 resolved after independent review.',
    ])->assertAccepted()->json('data.resume_request.id');
    $approver = moneyMovementOperator();
    Sanctum::actingAs($approver);
    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$resumeRequestId}/approve")
        ->assertOk();

    $this->getJson('/api/v1/operations/money-movement/events?per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.action', 'resumed')
        ->assertJsonPath('data.events.0.actor_user_id', $approver->identity_user_id)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/operations/money-movement/events?action=suspended')
        ->assertOk()
        ->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.action', 'suspended')
        ->assertJsonPath('data.events.0.source', 'operator_api')
        ->assertJsonPath('meta.total', 1);
});

it('rejects unsafe control-history pagination and filters', function (): void {
    allowMoneyMovementControl();
    Sanctum::actingAs(moneyMovementOperator());

    $this->getJson('/api/v1/operations/money-movement/events?per_page=101&action=deleted')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page', 'action']);
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

it('requires a different authenticated operator to approve resumption', function (): void {
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
    $resumeRequestId = $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => $resumeReason,
    ])->assertAccepted()
        ->assertJsonPath('data.resume_request.status', 'pending')
        ->json('data.resume_request.id');

    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$resumeRequestId}/approve")
        ->assertConflict()
        ->assertJsonPath('message', 'A different operator must approve the resume request.');
    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => false]);

    $approver = moneyMovementOperator();
    Sanctum::actingAs($approver);
    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$resumeRequestId}/approve")
        ->assertOk()
        ->assertJsonPath('data.resume_request.status', 'approved')
        ->assertJsonPath('data.resume_request.requested_by', $operator->identity_user_id)
        ->assertJsonPath('data.resume_request.approved_by', $approver->identity_user_id);

    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => true]);

    $this->assertDatabaseHas('money_movement_control_events', [
        'action' => 'resumed',
        'reason' => $resumeReason,
        'source' => 'operator_api_approval',
        'actor_user_id' => $approver->identity_user_id,
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

it('rejects resume requests while enabled and expired approvals while suspended', function (): void {
    allowMoneyMovementControl();
    $requester = moneyMovementOperator();
    Sanctum::actingAs($requester);

    $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => 'No suspension exists for this proposed request.',
    ])->assertConflict()
        ->assertJsonPath('message', 'Money movement is already enabled.');

    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Investigating an incident before approval testing.',
    ])->assertOk();
    $requestId = $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => 'Investigation completed but approval window elapsed.',
    ])->assertAccepted()->json('data.resume_request.id');
    DB::table('money_movement_resume_requests')->where('id', $requestId)->update([
        'expires_at' => now()->subMinute(),
    ]);

    Sanctum::actingAs(moneyMovementOperator());
    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$requestId}/approve")
        ->assertConflict()
        ->assertJsonPath('message', 'The resume request has expired. Submit a new request.');

    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => false]);
});

it('lets requesters cancel and independent approvers reject without resuming money movement', function (): void {
    allowMoneyMovementControl();
    $requester = moneyMovementOperator();
    $reviewer = moneyMovementOperator();
    Sanctum::actingAs($requester);
    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Incident investigation requires financial writes to stop.',
    ])->assertOk();
    $cancelRequestId = $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => 'Initial evidence suggested the incident was resolved.',
    ])->assertAccepted()->json('data.resume_request.id');

    Sanctum::actingAs($reviewer);
    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$cancelRequestId}/cancel", [
        'reason' => 'Reviewer cannot cancel a request they did not create.',
    ])->assertConflict()
        ->assertJsonPath('message', 'Only the operator who created the resume request may cancel it.');

    Sanctum::actingAs($requester);
    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$cancelRequestId}/cancel", [
        'reason' => 'New evidence requires continued investigation.',
    ])->assertOk()
        ->assertJsonPath('data.resume_request.status', 'cancelled')
        ->assertJsonPath('data.resume_request.closed_by', $requester->identity_user_id)
        ->assertJsonPath('data.resume_request.closure_reason', 'New evidence requires continued investigation.');

    $rejectRequestId = $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => 'Updated evidence is ready for independent review.',
    ])->assertAccepted()->json('data.resume_request.id');
    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$rejectRequestId}/reject", [
        'reason' => 'Requester cannot serve as their own independent reviewer.',
    ])->assertConflict()
        ->assertJsonPath(
            'message',
            'The requester must cancel their own request; a different operator may reject it.',
        );

    Sanctum::actingAs($reviewer);
    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$rejectRequestId}/reject", [
        'reason' => 'Reconciliation evidence is incomplete and needs review.',
    ])->assertOk()
        ->assertJsonPath('data.resume_request.status', 'rejected')
        ->assertJsonPath('data.resume_request.closed_by', $reviewer->identity_user_id)
        ->assertJsonPath('data.resume_request.closure_reason', 'Reconciliation evidence is incomplete and needs review.');

    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$rejectRequestId}/approve")
        ->assertConflict();
    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => false]);
    $this->assertDatabaseHas('money_movement_resume_requests', [
        'id' => $cancelRequestId,
        'status' => 'cancelled',
    ]);
    $this->assertDatabaseHas('money_movement_resume_requests', [
        'id' => $rejectRequestId,
        'status' => 'rejected',
    ]);
});

it('prevents an old approval request from resuming a newer suspension', function (): void {
    allowMoneyMovementControl();
    $requester = moneyMovementOperator();
    Sanctum::actingAs($requester);
    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Investigating the original settlement discrepancy.',
    ])->assertOk();
    $requestId = $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => 'The original settlement discrepancy appears resolved.',
    ])->assertAccepted()
        ->assertJsonPath('data.resume_request.control_revision', 2)
        ->json('data.resume_request.id');

    app(MoneyMovementControl::class)->suspend(
        'A newer reconciliation incident requires a separate investigation.',
        'reconciliation',
    );

    $approver = moneyMovementOperator();
    Sanctum::actingAs($approver);
    $this->postJson("/api/v1/operations/money-movement/resume-requests/{$requestId}/approve")
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'A newer suspension replaced this resume request. Submit a new request after investigating it.',
        );

    expect(app(MoneyMovementResumeRequestExpiry::class)->expire())->toBe(1);
    $this->getJson('/api/v1/operations/money-movement/resume-requests?status=superseded')
        ->assertOk()
        ->assertJsonPath('data.resume_requests.0.id', $requestId)
        ->assertJsonPath('data.resume_requests.0.status', 'superseded');
    $this->assertDatabaseHas('money_movement_controls', [
        'name' => 'global',
        'enabled' => false,
        'revision' => 3,
    ]);
});

it('durably notifies only active verified independent approvers', function (): void {
    allowMoneyMovementControl();
    config()->set('notification.operations_url', 'https://operations.example.test/resume-requests');
    $requester = moneyMovementOperator();
    $eligibleReviewer = moneyMovementOperator();
    $unverifiedReviewer = User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => null,
    ]);
    $inactiveReviewer = moneyMovementOperator();
    configureMoneyMovementApprovers([
        ['user' => $requester, 'staff_status' => 'active'],
        ['user' => $eligibleReviewer, 'staff_status' => 'active'],
        ['user' => $unverifiedReviewer, 'staff_status' => 'active'],
        ['user' => $inactiveReviewer, 'staff_status' => 'inactive'],
    ]);

    Sanctum::actingAs($requester);
    $this->postJson('/api/v1/operations/money-movement/suspend', [
        'reason' => 'Settlement review requires financial writes to remain stopped.',
    ])->assertOk();
    $resumeReason = 'Settlement evidence is ready for independent approval.';
    $requestId = $this->postJson('/api/v1/operations/money-movement/resume', [
        'reason' => $resumeReason,
    ])->assertAccepted()->json('data.resume_request.id');

    $messages = app(EmailOutboxRepository::class)->pending(10);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->email()->recipient()->value())->toBe($eligibleReviewer->email)
        ->and($messages[0]->email()->subject()->value())->toBe('Money movement resume approval required')
        ->and($messages[0]->email()->body()->value())->toContain($requestId)
        ->toContain('https://operations.example.test/resume-requests')
        ->not->toContain($resumeReason)
        ->not->toContain($requester->email);
});

it('does not create a resume request when its durable notification cannot be recorded', function (): void {
    app()->instance(MoneyMovementResumeNotifier::class, new class implements MoneyMovementResumeNotifier
    {
        public function pending(MoneyMovementResumeRequest $request): void
        {
            throw new RuntimeException('Outbox storage unavailable.');
        }
    });
    $control = app(MoneyMovementControl::class);
    $control->suspend('Notification transaction rollback is under test.', 'test');

    expect(fn () => app(MoneyMovementResumeApproval::class)->request(
        'Evidence is ready but its notification cannot be stored.',
        UserId::generate(),
    ))->toThrow(RuntimeException::class, 'Outbox storage unavailable.');

    $this->assertDatabaseCount('money_movement_resume_requests', 0);
});
