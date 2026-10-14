<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DemoBankingSeeder;
use Database\Seeders\MoneyMovementApproverSeeder;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ledger\Application\Reconciliation\LedgerReconciliation;

uses(RefreshDatabase::class);

it('creates repeatable funded customer and authorized administrator demo data', function (): void {
    $this->seed(DemoBankingSeeder::class);
    $this->seed(DemoBankingSeeder::class);

    $customer = User::query()->where('email', 'customer@example.com')->firstOrFail();
    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    $approver = User::query()->where('email', 'approver@example.com')->firstOrFail();

    expect(Hash::check('DemoPassword123!', $customer->password))->toBeTrue()
        ->and(Hash::check('DemoPassword123!', $admin->password))->toBeTrue()
        ->and(Hash::check('DemoPassword123!', $approver->password))->toBeTrue()
        ->and($approver->email_verified_at)->not->toBeNull()
        ->and(DB::table('users')->count())->toBe(5)
        ->and(DB::table('customers')->count())->toBe(3)
        ->and(DB::table('accounts')->count())->toBe(3)
        ->and(DB::table('ledger_entries')->count())->toBe(2)
        ->and(DB::table('ledger_postings')->count())->toBe(4)
        ->and(DB::table('administration_permissions')->count())->toBe(29)
        ->and(DB::table('role_permission_assignments')->count())->toBe(32)
        ->and(app(AuthorizationChecker::class)->allows(
            new UserId($approver->identity_user_id),
            new Permission('money_movement.approve'),
        ))->toBeTrue()
        ->and(app(AuthorizationChecker::class)->allows(
            new UserId($approver->identity_user_id),
            new Permission('money_movement.audit'),
        ))->toBeTrue()
        ->and(app(AuthorizationChecker::class)->allows(
            new UserId($approver->identity_user_id),
            new Permission('operations.view'),
        ))->toBeTrue()
        ->and(app(AuthorizationChecker::class)->allows(
            new UserId($approver->identity_user_id),
            new Permission('money_movement.manage'),
        ))->toBeFalse()
        ->and(app(LedgerReconciliation::class)->inspect()->isReconciled())->toBeTrue();

    $this->assertDatabaseHas('accounts', [
        'number' => '1000000001',
        'status' => 'active',
    ])->assertDatabaseHas('ledger_balances', [
        'ledger_id' => '40000000-0000-4000-8000-000000000001',
        'balance_minor_units' => 25000000,
    ])->assertDatabaseHas('staff', [
        'user_id' => $admin->identity_user_id,
        'status' => 'active',
    ]);
});

it('can seed the independent money movement approver on its own', function (): void {
    $this->seed(MoneyMovementApproverSeeder::class);
    $this->seed(MoneyMovementApproverSeeder::class);

    $approver = User::query()->where('email', 'approver@example.com')->firstOrFail();

    expect(User::query()->where('email', 'approver@example.com')->count())->toBe(1)
        ->and($approver->email_verified_at)->not->toBeNull()
        ->and(app(AuthorizationChecker::class)->allows(
            new UserId($approver->identity_user_id),
            new Permission('money_movement.approve'),
        ))->toBeTrue()
        ->and(app(AuthorizationChecker::class)->allows(
            new UserId($approver->identity_user_id),
            new Permission('money_movement.audit'),
        ))->toBeTrue()
        ->and(app(AuthorizationChecker::class)->allows(
            new UserId($approver->identity_user_id),
            new Permission('operations.view'),
        ))->toBeTrue()
        ->and(app(AuthorizationChecker::class)->allows(
            new UserId($approver->identity_user_id),
            new Permission('money_movement.manage'),
        ))->toBeFalse();

    $this->assertDatabaseHas('staff', [
        'user_id' => $approver->identity_user_id,
        'status' => 'active',
    ])->assertDatabaseHas('administration_roles', [
        'name' => 'demo_money_movement_approver',
        'status' => 'active',
    ]);
});
