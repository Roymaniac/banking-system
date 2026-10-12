<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

/** Creates the staff access records needed by the operations navigation test. */
function createOperatorContext(User $user, string $staffStatus = 'active'): void
{
    $departmentId = Uuid::generate()->value();
    $staffId = Uuid::generate()->value();
    $roleId = Uuid::generate()->value();
    $inactiveRoleId = Uuid::generate()->value();
    $viewPermissionId = Uuid::generate()->value();
    $managePermissionId = Uuid::generate()->value();

    DB::table('departments')->insert([
        'id' => $departmentId,
        'code' => 'OPS',
        'name' => 'Banking Operations',
        'status' => 'active',
        'created_at' => now(),
        'version' => 1,
    ]);
    DB::table('staff')->insert([
        'id' => $staffId,
        'user_id' => $user->identity_user_id,
        'employee_number' => 'EMP-0001',
        'department_id' => $departmentId,
        'job_title' => 'Operations Manager',
        'status' => $staffStatus,
        'hired_at' => now(),
        'deactivated_at' => $staffStatus === 'active' ? null : now(),
        'version' => 1,
    ]);
    DB::table('administration_roles')->insert([
        [
            'id' => $roleId,
            'name' => 'operations-manager',
            'label' => 'Operations Manager',
            'status' => 'active',
            'created_at' => now(),
            'deactivated_at' => null,
            'version' => 1,
        ],
        [
            'id' => $inactiveRoleId,
            'name' => 'old-role',
            'label' => 'Old Role',
            'status' => 'inactive',
            'created_at' => now(),
            'deactivated_at' => now(),
            'version' => 1,
        ],
    ]);
    DB::table('administration_permissions')->insert([
        [
            'id' => $viewPermissionId,
            'name' => 'operations.view',
            'label' => 'View operations',
            'created_at' => now(),
            'version' => 1,
        ],
        [
            'id' => $managePermissionId,
            'name' => 'money_movement.manage',
            'label' => 'Manage money movement',
            'created_at' => now(),
            'version' => 1,
        ],
    ]);
    DB::table('staff_role_assignments')->insert([
        ['role_id' => $roleId, 'staff_id' => $staffId, 'assigned_at' => now()],
        ['role_id' => $inactiveRoleId, 'staff_id' => $staffId, 'assigned_at' => now()],
    ]);
    DB::table('role_permission_assignments')->insert([
        ['role_id' => $roleId, 'permission_id' => $viewPermissionId, 'granted_at' => now()],
        ['role_id' => $inactiveRoleId, 'permission_id' => $managePermissionId, 'granted_at' => now()],
    ]);
}

it('returns the active operator roles and effective permissions for frontend navigation', function (): void {
    $user = User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
    createOperatorContext($user);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/operator-context')
        ->assertOk()
        ->assertJsonPath('data.operator.employee_number', 'EMP-0001')
        ->assertJsonPath('data.operator.job_title', 'Operations Manager')
        ->assertJsonPath('data.operator.department.code', 'OPS')
        ->assertJsonPath('data.operator.roles.0.name', 'operations-manager')
        ->assertJsonPath('data.operator.permissions.0', 'operations.view')
        ->assertJsonCount(1, 'data.operator.roles')
        ->assertJsonCount(1, 'data.operator.permissions')
        ->assertJsonMissingPath('data.operator.user_id');
});

it('requires authentication and an active staff record', function (): void {
    $this->getJson('/api/v1/auth/operator-context')->assertUnauthorized();

    $user = User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
    createOperatorContext($user, 'inactive');
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/operator-context')
        ->assertForbidden()
        ->assertJsonPath('message', 'Active staff access is required.');
});
