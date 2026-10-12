<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Risk\TransferRiskCaseSla;
use Transaction\Application\Risk\TransferRiskMonitor;

uses(RefreshDatabase::class);

function transferRiskOperator(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowTransferRiskReview(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

function configureTransferRiskReviewer(User $reviewer): void
{
    $departmentId = Uuid::generate()->value();
    $roleId = Uuid::generate()->value();
    $permissionId = Uuid::generate()->value();

    DB::table('departments')->insert([
        'id' => $departmentId,
        'code' => 'RISK',
        'name' => 'Risk Operations',
        'status' => 'active',
        'created_at' => now(),
        'version' => 1,
    ]);
    DB::table('administration_roles')->insert([
        'id' => $roleId,
        'name' => 'risk-case-reviewer',
        'label' => 'Risk Case Reviewer',
        'status' => 'active',
        'created_at' => now(),
        'version' => 1,
    ]);
    DB::table('administration_permissions')->insert([
        'id' => $permissionId,
        'name' => 'risk_cases.review',
        'label' => 'Review transfer risk cases',
        'created_at' => now(),
        'version' => 1,
    ]);
    DB::table('role_permission_assignments')->insert([
        'role_id' => $roleId,
        'permission_id' => $permissionId,
        'granted_at' => now(),
    ]);
    $staffId = Uuid::generate()->value();
    DB::table('staff')->insert([
        'id' => $staffId,
        'user_id' => $reviewer->identity_user_id,
        'employee_number' => 'RISK-0001',
        'department_id' => $departmentId,
        'job_title' => 'Risk Reviewer',
        'status' => 'active',
        'hired_at' => now(),
        'version' => 1,
    ]);
    DB::table('staff_role_assignments')->insert([
        'role_id' => $roleId,
        'staff_id' => $staffId,
        'assigned_at' => now(),
    ]);
}

/** @return array{account_id: string, case_id: string} */
function createTransferRiskCase(string $number = '1234567890'): array
{
    $owner = transferRiskOperator();
    $customerId = Uuid::generate()->value();
    $accountId = AccountId::generate()->value();
    $caseId = Uuid::generate()->value();

    DB::table('customers')->insert([
        'id' => $customerId,
        'user_id' => $owner->identity_user_id,
        'first_name' => 'Risk',
        'middle_name' => null,
        'last_name' => 'Customer',
        'date_of_birth' => '1990-01-01',
        'registered_at' => now(),
        'version' => 1,
    ]);
    DB::table('accounts')->insert([
        'id' => $accountId,
        'customer_id' => $customerId,
        'type' => 'savings',
        'currency' => 'NGN',
        'created_at' => now(),
        'version' => 3,
        'number' => $number,
        'status' => 'active',
    ]);
    DB::table('transfer_risk_cases')->insert([
        'id' => $caseId,
        'account_id' => $accountId,
        'rule' => 'outgoing_transfer_velocity',
        'status' => 'pending',
        'window_minutes' => 60,
        'maximum_transfers' => 10,
        'detected_at' => now(),
        'due_at' => now()->addMinutes(30),
        'open_key' => hash('sha256', $accountId.'|outgoing_transfer_velocity'),
        'version' => 1,
    ]);

    return ['account_id' => $accountId, 'case_id' => $caseId];
}

it('requires privileged authentication and an explicit risk permission', function (): void {
    $this->getJson('/api/v1/operations/transfer-risk-cases')->assertUnauthorized();

    Sanctum::actingAs(transferRiskOperator(), ['privileged']);
    $this->getJson('/api/v1/operations/transfer-risk-cases')->assertForbidden();
    $this->postJson('/api/v1/operations/transfer-risk-cases/'.Uuid::generate()->value().'/claim')
        ->assertForbidden();
});

it('lists a bounded queue with only a masked account identifier', function (): void {
    $case = createTransferRiskCase('1734567890');
    allowTransferRiskReview();
    Sanctum::actingAs(transferRiskOperator(), ['privileged']);

    $this->getJson('/api/v1/operations/transfer-risk-cases?status=pending&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.risk_cases.0.id', $case['case_id'])
        ->assertJsonPath('data.risk_cases.0.account_last_four', '7890')
        ->assertJsonPath('data.risk_cases.0.status', 'pending')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonMissingPath('data.risk_cases.0.account_id');

    $this->getJson('/api/v1/operations/transfer-risk-cases?status=unknown&per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'per_page']);
});

it('allows only the claiming reviewer to resolve a case', function (): void {
    $case = createTransferRiskCase();
    $firstReviewer = transferRiskOperator();
    $secondReviewer = transferRiskOperator();
    allowTransferRiskReview();
    $url = "/api/v1/operations/transfer-risk-cases/{$case['case_id']}";

    Sanctum::actingAs($firstReviewer, ['privileged']);
    $this->postJson($url.'/claim')
        ->assertOk()
        ->assertJsonPath('data.risk_case.status', 'in_review')
        ->assertJsonPath('data.risk_case.claimed_by', $firstReviewer->identity_user_id);

    Sanctum::actingAs($secondReviewer, ['privileged']);
    $this->postJson($url.'/claim')->assertConflict();
    $this->postJson($url.'/resolve', [
        'resolution' => 'confirmed_risk',
        'note' => 'The account owner did not recognize this activity.',
    ])->assertConflict();

    Sanctum::actingAs($firstReviewer, ['privileged']);
    $this->postJson($url.'/resolve', [
        'resolution' => 'cleared',
        'note' => 'The account owner confirmed the recent transfer activity.',
    ])->assertOk()
        ->assertJsonPath('data.risk_case.status', 'resolved')
        ->assertJsonPath('data.risk_case.resolution', 'cleared')
        ->assertJsonPath('data.risk_case.resolved_by', $firstReviewer->identity_user_id);
    $this->postJson($url.'/resolve', [
        'resolution' => 'cleared',
        'note' => 'A repeated resolution must not change this closed case.',
    ])->assertConflict();

    $this->assertDatabaseHas('transfer_risk_cases', [
        'id' => $case['case_id'],
        'status' => 'resolved',
        'open_key' => null,
        'version' => 3,
    ]);
});

it('keeps repeated blocks in one open case while auditing every attempt', function (): void {
    $case = createTransferRiskCase();
    DB::table('transfer_risk_cases')->delete();
    configureTransferRiskReviewer(transferRiskOperator());
    $monitor = app(TransferRiskMonitor::class);
    $accountId = new AccountId($case['account_id']);

    $monitor->velocityBlocked($accountId, 60, 10);
    $monitor->velocityBlocked($accountId, 60, 10);

    $this->assertDatabaseCount('transfer_risk_cases', 1)
        ->assertDatabaseCount('security_events', 2)
        ->assertDatabaseCount('email_outbox', 1);

    expect(DB::table('transfer_risk_cases')->value('due_at'))->not->toBeNull();
});

it('sends one reminder and one overdue escalation without flooding reviewers', function (): void {
    $case = createTransferRiskCase();
    configureTransferRiskReviewer(transferRiskOperator());
    DB::table('transfer_risk_cases')->where('id', $case['case_id'])->update([
        'due_at' => now()->addMinutes(5),
        'reminded_at' => null,
        'escalated_at' => null,
    ]);
    $sla = app(TransferRiskCaseSla::class);

    expect($sla->processDue())->toBe(['reminded' => 1, 'escalated' => 0])
        ->and($sla->processDue())->toBe(['reminded' => 0, 'escalated' => 0]);
    $this->assertDatabaseCount('email_outbox', 1);

    DB::table('transfer_risk_cases')->where('id', $case['case_id'])->update([
        'due_at' => now()->subMinute(),
    ]);

    expect($sla->processDue())->toBe(['reminded' => 0, 'escalated' => 1])
        ->and($sla->processDue())->toBe(['reminded' => 0, 'escalated' => 0]);
    $this->assertDatabaseCount('email_outbox', 2);
    expect(DB::table('transfer_risk_cases')->value('reminded_at'))->not->toBeNull()
        ->and(DB::table('transfer_risk_cases')->value('escalated_at'))->not->toBeNull();
});
