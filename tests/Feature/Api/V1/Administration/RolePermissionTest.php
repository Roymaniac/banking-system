<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function rolePermissionApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowRolePermissionApi(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

function createApiRole(string $name = 'operations_manager'): string
{
    return test()->postJson('/api/v1/administration/roles', [
        'name' => $name,
        'label' => 'Operations Manager',
    ])->assertCreated()->json('data.role.id');
}

function createApiPermission(string $name = 'accounts.view'): string
{
    return test()->postJson('/api/v1/administration/permissions', [
        'name' => $name,
        'label' => 'View customer accounts',
    ])->assertCreated()->json('data.permission.id');
}

it('protects role management with authentication and permission checks', function (): void {
    $this->postJson('/api/v1/administration/roles')->assertUnauthorized();

    Sanctum::actingAs(rolePermissionApiUser());

    $this->postJson('/api/v1/administration/roles', [
        'name' => 'auditor',
        'label' => 'Internal Auditor',
    ])->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
});

it('creates and deactivates a role', function (): void {
    allowRolePermissionApi();
    Sanctum::actingAs(rolePermissionApiUser());

    $roleId = createApiRole();

    $this->deleteJson("/api/v1/administration/roles/{$roleId}")
        ->assertOk()
        ->assertJsonPath('message', 'Role deactivated successfully.');

    $this->assertDatabaseHas('administration_roles', [
        'id' => $roleId,
        'name' => 'operations_manager',
        'status' => 'inactive',
    ]);

    $this->deleteJson("/api/v1/administration/roles/{$roleId}")->assertConflict();
});

it('creates permissions and rejects duplicate catalogue names', function (): void {
    allowRolePermissionApi();
    Sanctum::actingAs(rolePermissionApiUser());

    $permissionId = createApiPermission();

    $this->assertDatabaseHas('administration_permissions', [
        'id' => $permissionId,
        'name' => 'accounts.view',
        'label' => 'View customer accounts',
    ]);

    $this->postJson('/api/v1/administration/permissions', [
        'name' => 'ACCOUNTS.VIEW',
        'label' => 'Duplicate permission',
    ])->assertConflict();
});

it('assigns a role to active staff only once', function (): void {
    allowRolePermissionApi();
    Sanctum::actingAs(rolePermissionApiUser());

    $departmentId = $this->postJson('/api/v1/administration/departments', [
        'code' => 'SEC',
        'name' => 'Security',
    ])->assertCreated()->json('data.department.id');
    $staffUser = rolePermissionApiUser();
    $staffId = $this->postJson('/api/v1/administration/staff', [
        'user_id' => $staffUser->identity_user_id,
        'employee_number' => 'EMP-SEC-1',
        'department_id' => $departmentId,
        'job_title' => 'Security Administrator',
    ])->assertCreated()->json('data.staff.id');
    $roleId = createApiRole('security_admin');

    $this->postJson("/api/v1/administration/roles/{$roleId}/staff/{$staffId}")
        ->assertOk()
        ->assertJsonPath('message', 'Role assigned to staff successfully.');
    $this->assertDatabaseHas('staff_role_assignments', [
        'role_id' => $roleId,
        'staff_id' => $staffId,
    ]);

    $this->postJson("/api/v1/administration/roles/{$roleId}/staff/{$staffId}")
        ->assertConflict();
});

it('grants and revokes a role permission', function (): void {
    allowRolePermissionApi();
    Sanctum::actingAs(rolePermissionApiUser());
    $roleId = createApiRole('account_reader');
    $permissionId = createApiPermission('accounts.read');
    $url = "/api/v1/administration/roles/{$roleId}/permissions/{$permissionId}";

    $this->putJson($url)
        ->assertOk()
        ->assertJsonPath('message', 'Permission granted to role successfully.');
    $this->assertDatabaseHas('role_permission_assignments', [
        'role_id' => $roleId,
        'permission_id' => $permissionId,
    ]);
    $this->putJson($url)->assertConflict();

    $this->deleteJson($url)
        ->assertOk()
        ->assertJsonPath('message', 'Permission revoked from role successfully.');
    $this->assertDatabaseMissing('role_permission_assignments', [
        'role_id' => $roleId,
        'permission_id' => $permissionId,
    ]);
    $this->deleteJson($url)->assertConflict();
});

it('validates role and permission names before calling the domain', function (): void {
    allowRolePermissionApi();
    Sanctum::actingAs(rolePermissionApiUser());

    $this->postJson('/api/v1/administration/roles', [
        'name' => 'not valid',
        'label' => 'x',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'label']);

    $this->postJson('/api/v1/administration/permissions', [
        'name' => 'missing-dot',
        'label' => 'x',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'label']);
});
