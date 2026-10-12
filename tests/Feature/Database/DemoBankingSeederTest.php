<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DemoBankingSeeder;
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

    expect(Hash::check('DemoPassword123!', $customer->password))->toBeTrue()
        ->and(Hash::check('DemoPassword123!', $admin->password))->toBeTrue()
        ->and(DB::table('users')->count())->toBe(4)
        ->and(DB::table('customers')->count())->toBe(3)
        ->and(DB::table('accounts')->count())->toBe(3)
        ->and(DB::table('ledger_entries')->count())->toBe(2)
        ->and(DB::table('ledger_postings')->count())->toBe(4)
        ->and(DB::table('administration_permissions')->count())->toBe(29)
        ->and(DB::table('role_permission_assignments')->count())->toBe(29)
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
