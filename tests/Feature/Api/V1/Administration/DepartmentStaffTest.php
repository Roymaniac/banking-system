<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function administrationApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowAdministrationApi(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

function createAdministrationDepartment(string $code, string $name): string
{
    return test()->postJson('/api/v1/administration/departments', [
        'code' => $code,
        'name' => $name,
    ])->assertCreated()->json('data.department.id');
}

it('requires authentication and explicit administration permissions', function (): void {
    $this->postJson('/api/v1/administration/departments')->assertUnauthorized();

    Sanctum::actingAs(administrationApiUser());

    $this->postJson('/api/v1/administration/departments', [
        'code' => 'OPS',
        'name' => 'Operations',
    ])->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
});

it('creates renames and deactivates a department', function (): void {
    allowAdministrationApi();
    Sanctum::actingAs(administrationApiUser());

    $departmentId = createAdministrationDepartment('OPS', 'Operations');

    $this->patchJson("/api/v1/administration/departments/{$departmentId}", [
        'name' => 'Banking Operations',
    ])->assertOk()
        ->assertJsonPath('message', 'Department renamed successfully.');
    $this->deleteJson("/api/v1/administration/departments/{$departmentId}")
        ->assertOk()
        ->assertJsonPath('message', 'Department deactivated successfully.');

    $this->assertDatabaseHas('departments', [
        'id' => $departmentId,
        'code' => 'OPS',
        'name' => 'Banking Operations',
        'status' => 'inactive',
    ]);
});

it('rejects duplicate department codes and changes to inactive departments', function (): void {
    allowAdministrationApi();
    Sanctum::actingAs(administrationApiUser());
    $departmentId = createAdministrationDepartment('RISK', 'Risk');

    $this->postJson('/api/v1/administration/departments', [
        'code' => 'risk',
        'name' => 'Duplicate Risk',
    ])->assertConflict();
    $this->deleteJson("/api/v1/administration/departments/{$departmentId}")->assertOk();
    $this->patchJson("/api/v1/administration/departments/{$departmentId}", [
        'name' => 'Changed Risk',
    ])->assertConflict();
});

it('hires transfers and deactivates a staff member', function (): void {
    allowAdministrationApi();
    Sanctum::actingAs(administrationApiUser());
    $operationsId = createAdministrationDepartment('OPS', 'Operations');
    $riskId = createAdministrationDepartment('RISK', 'Risk');
    $staffUser = administrationApiUser();

    $staffId = $this->postJson('/api/v1/administration/staff', [
        'user_id' => $staffUser->identity_user_id,
        'employee_number' => 'EMP-1001',
        'department_id' => $operationsId,
        'job_title' => 'Operations Analyst',
    ])->assertCreated()
        ->assertJsonPath('data.staff.status', 'active')
        ->json('data.staff.id');

    $this->patchJson("/api/v1/administration/staff/{$staffId}/department", [
        'department_id' => $riskId,
    ])->assertOk()
        ->assertJsonPath('message', 'Staff member transferred successfully.');
    $this->deleteJson("/api/v1/administration/staff/{$staffId}")
        ->assertOk()
        ->assertJsonPath('message', 'Staff member deactivated successfully.');

    $this->assertDatabaseHas('staff', [
        'id' => $staffId,
        'department_id' => $riskId,
        'status' => 'inactive',
    ]);
});

it('validates staff input and requires an active department', function (): void {
    allowAdministrationApi();
    Sanctum::actingAs(administrationApiUser());
    $departmentId = createAdministrationDepartment('OLD', 'Old Department');
    $this->deleteJson("/api/v1/administration/departments/{$departmentId}")->assertOk();

    $this->postJson('/api/v1/administration/staff', [
        'user_id' => UserId::generate()->value(),
        'employee_number' => 'EMP-1002',
        'department_id' => $departmentId,
        'job_title' => 'Analyst',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Staff can only be assigned to an active department.');

    $this->postJson('/api/v1/administration/staff', [
        'user_id' => 'invalid',
        'department_id' => 'invalid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id', 'employee_number', 'department_id', 'job_title']);
});
