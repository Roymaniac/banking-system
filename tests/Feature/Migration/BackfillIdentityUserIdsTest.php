<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

it('backfills old authentication users before requiring identity UUIDs', function (): void {
    // Recreate the state left by the older migration: the column existed,
    // but Laravel users created before Identity integration still had null.
    Schema::table('users', function (Blueprint $table): void {
        $table->uuid('identity_user_id')->nullable()->change();
    });

    DB::table('users')->insert([
        'identity_user_id' => null,
        'identity_version' => 1,
        'name' => 'Legacy User',
        'email' => 'legacy@example.com',
        'password' => password_hash('safe-test-password', PASSWORD_BCRYPT),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_10_06_000000_backfill_identity_user_ids.php');
    $migration->up();

    $backfilledId = DB::table('users')->value('identity_user_id');

    expect($backfilledId)->toBeString()
        ->and((new Uuid($backfilledId))->value())->toBe($backfilledId)
        ->and(fn () => DB::table('users')->insert([
            'identity_user_id' => null,
            'identity_version' => 1,
            'name' => 'Unlinked User',
            'email' => 'unlinked@example.com',
            'password' => password_hash('safe-test-password', PASSWORD_BCRYPT),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
        ->and(Schema::hasColumn('users', 'identity_user_id'))->toBeTrue();
});
