<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Security\PruneExpiredAuthenticationRecords;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('removes only expired MFA challenges and access tokens', function (): void {
    $user = User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
    ]);

    DB::table('multi_factor_login_challenges')->insert([
        [
            'token_hash' => hash('sha256', 'expired-challenge'),
            'user_id' => $user->identity_user_id,
            'device_name' => 'Expired challenge',
            'purpose' => 'login',
            'context_hash' => null,
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(6),
        ],
        [
            'token_hash' => hash('sha256', 'active-challenge'),
            'user_id' => $user->identity_user_id,
            'device_name' => 'Active challenge',
            'purpose' => 'login',
            'context_hash' => null,
            'attempts' => 0,
            'expires_at' => now()->addMinute(),
            'created_at' => now(),
        ],
    ]);

    $expiredToken = $user->createToken('Expired token', ['customer'], now()->subMinute());
    $activeToken = $user->createToken('Active token', ['customer'], now()->addMinute());
    $nonExpiringToken = $user->createToken('Non-expiring token', ['customer']);

    $result = app(PruneExpiredAuthenticationRecords::class)->prune();

    expect($result)->toBe(['mfa_challenges' => 1, 'access_tokens' => 1]);
    $this->assertDatabaseMissing('multi_factor_login_challenges', [
        'token_hash' => hash('sha256', 'expired-challenge'),
    ])->assertDatabaseHas('multi_factor_login_challenges', [
        'token_hash' => hash('sha256', 'active-challenge'),
    ])->assertDatabaseMissing('personal_access_tokens', [
        'id' => $expiredToken->accessToken->getKey(),
    ])->assertDatabaseHas('personal_access_tokens', [
        'id' => $activeToken->accessToken->getKey(),
    ])->assertDatabaseHas('personal_access_tokens', [
        'id' => $nonExpiringToken->accessToken->getKey(),
    ]);
});
