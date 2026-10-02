<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function directoryApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowDirectoryApi(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

function directoryDepartment(string $code, string $name): string
{
    return test()->postJson('/api/v1/administration/departments', compact('code', 'name'))
        ->assertCreated()
        ->json('data.department.id');
}

it('protects administration directory reads', function (): void {
    $this->getJson('/api/v1/administration/departments')->assertUnauthorized();

    Sanctum::actingAs(directoryApiUser());

    $this->getJson('/api/v1/administration/departments')
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
});

it('lists searches filters and paginates departments', function (): void {
    allowDirectoryApi();
    Sanctum::actingAs(directoryApiUser());
    directoryDepartment('OPS', 'Operations');
    $riskId = directoryDepartment('RISK', 'Risk Management');
    directoryDepartment('TECH', 'Technology');
    $this->deleteJson("/api/v1/administration/departments/{$riskId}")->assertOk();

    $this->getJson('/api/v1/administration/departments?search=Risk&status=inactive&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.departments')
        ->assertJsonPath('data.departments.0.code', 'RISK')
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.last_page', 1);
});

it('shows staff with its department and assigned roles', function (): void {
    allowDirectoryApi();
    Sanctum::actingAs(directoryApiUser());
    $departmentId = directoryDepartment('SEC', 'Security');
    $staffUser = directoryApiUser();
    $staffId = $this->postJson('/api/v1/administration/staff', [
        'user_id' => $staffUser->identity_user_id,
        'employee_number' => 'EMP-DIR-1',
        'department_id' => $departmentId,
        'job_title' => 'Security Analyst',
    ])->assertCreated()->json('data.staff.id');
    $roleId = $this->postJson('/api/v1/administration/roles', [
        'name' => 'security_reader',
        'label' => 'Security Reader',
    ])->assertCreated()->json('data.role.id');
    $this->postJson("/api/v1/administration/roles/{$roleId}/staff/{$staffId}")->assertOk();

    $this->getJson("/api/v1/administration/staff/{$staffId}")
        ->assertOk()
        ->assertJsonPath('data.staff.employee_number', 'EMP-DIR-1')
        ->assertJsonPath('data.staff.department.code', 'SEC')
        ->assertJsonPath('data.staff.roles.0.name', 'security_reader');

    $this->getJson('/api/v1/administration/staff?search=Analyst&status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data.staff');
});

it('shows roles with permissions and permissions with roles', function (): void {
    allowDirectoryApi();
    Sanctum::actingAs(directoryApiUser());
    $roleId = $this->postJson('/api/v1/administration/roles', [
        'name' => 'account_auditor',
        'label' => 'Account Auditor',
    ])->assertCreated()->json('data.role.id');
    $permissionId = $this->postJson('/api/v1/administration/permissions', [
        'name' => 'accounts.audit',
        'label' => 'Audit customer accounts',
    ])->assertCreated()->json('data.permission.id');
    $this->putJson("/api/v1/administration/roles/{$roleId}/permissions/{$permissionId}")->assertOk();

    $this->getJson("/api/v1/administration/roles/{$roleId}")
        ->assertOk()
        ->assertJsonPath('data.role.permissions.0.name', 'accounts.audit')
        ->assertJsonPath('data.role.staff_count', 0);
    $this->getJson("/api/v1/administration/permissions/{$permissionId}")
        ->assertOk()
        ->assertJsonPath('data.permission.roles.0.name', 'account_auditor');

    $this->getJson('/api/v1/administration/roles?search=Auditor')
        ->assertOk()->assertJsonCount(1, 'data.roles');
    $this->getJson('/api/v1/administration/permissions?search=audit')
        ->assertOk()->assertJsonCount(1, 'data.permissions');
});

it('returns safe not-found responses and validates list bounds', function (): void {
    allowDirectoryApi();
    Sanctum::actingAs(directoryApiUser());
    $missing = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    $this->getJson("/api/v1/administration/departments/{$missing}")->assertNotFound();
    $this->getJson("/api/v1/administration/staff/{$missing}")->assertNotFound();
    $this->getJson("/api/v1/administration/roles/{$missing}")->assertNotFound();
    $this->getJson("/api/v1/administration/permissions/{$missing}")->assertNotFound();

    $this->getJson('/api/v1/administration/departments?page=0&per_page=101&status=unknown')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page', 'per_page', 'status']);
});
