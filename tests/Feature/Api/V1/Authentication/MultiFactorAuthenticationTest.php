<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\MultiFactor\TotpAuthenticator;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(RefreshDatabase::class);

function multiFactorUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => 'mfa-customer@example.com',
        'email_verified_at' => now(),
        'password' => 'CorrectPassword1!',
    ]);
}

function usePredictableTotp(): void
{
    app()->instance(TotpAuthenticator::class, new class implements TotpAuthenticator
    {
        public function generateSecret(): string
        {
            return 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        }

        public function provisioningUri(EmailAddress $email, string $issuer, string $secret): string
        {
            return 'otpauth://totp/test?secret='.$secret;
        }

        public function verify(string $secret, string $code, DateTimeImmutable $at): bool
        {
            return $secret === 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP' && $code === '123456';
        }
    });
}

/** @return array{user: User, token: string, recovery_codes: list<string>} */
function enableMultiFactor(TestCase $test): array
{
    usePredictableTotp();
    $user = multiFactorUser();
    $token = $user->createToken('enrollment-device')->plainTextToken;
    $user->createToken('old-device');

    $test->withToken($token)->postJson('/api/v1/auth/mfa/setup', [
        'current_password' => 'CorrectPassword1!',
    ])->assertOk()
        ->assertJsonPath('data.secret', 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');

    $encryptedSecret = (string) DB::table('user_multi_factor_authentication')
        ->value('encrypted_secret');
    expect($encryptedSecret)->not->toContain('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');

    $recoveryResponse = $test->withToken($token)->postJson('/api/v1/auth/mfa/confirm', [
        'code' => '123456',
    ])->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonCount(10, 'data.recovery_codes');
    $recoveryCodes = collect($recoveryResponse->json('data.recovery_codes'))
        ->filter(static fn (mixed $value): bool => is_string($value))
        ->values()
        ->all();

    return ['user' => $user, 'token' => $token, 'recovery_codes' => $recoveryCodes];
}

it('encrypts enrollment secrets, hashes recovery codes, and removes older sessions', function (): void {
    $enabled = enableMultiFactor($this);

    $this->withToken($enabled['token'])->getJson('/api/v1/auth/mfa')
        ->assertOk()
        ->assertJsonPath('data.enabled', true);

    expect(DB::table('personal_access_tokens')->count())->toBe(1);
    $storedHashes = (string) DB::table('user_multi_factor_authentication')
        ->value('recovery_code_hashes');
    expect($storedHashes)
        ->not->toContain($enabled['recovery_codes'][0])
        ->and(json_decode($storedHashes, true, flags: JSON_THROW_ON_ERROR))->toHaveCount(10);

    $this->withToken($enabled['token'])->postJson('/api/v1/auth/mfa/setup', [
        'current_password' => 'CorrectPassword1!',
    ])->assertConflict()
        ->assertJsonPath('message', 'Multi-factor authentication is already enabled.');
});

it('withholds an API token until a valid second factor completes login', function (): void {
    $enabled = enableMultiFactor($this);
    $existingTokens = DB::table('personal_access_tokens')->count();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $enabled['user']->email,
        'password' => 'CorrectPassword1!',
        'device_name' => 'new-phone',
    ])->assertAccepted()
        ->assertJsonPath('data.mfa_required', true)
        ->assertJsonMissingPath('data.access_token');

    $challengeToken = (string) $login->json('data.challenge_token');
    expect(DB::table('personal_access_tokens')->count())->toBe($existingTokens);
    $this->assertDatabaseHas('multi_factor_login_challenges', [
        'token_hash' => hash('sha256', $challengeToken),
        'device_name' => 'new-phone',
    ]);

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'challenge_token' => $challengeToken,
        'code' => '123456',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['access_token', 'expires_at', 'user']]);

    expect(DB::table('personal_access_tokens')->count())->toBe($existingTokens + 1);
    $this->assertDatabaseMissing('multi_factor_login_challenges', [
        'token_hash' => hash('sha256', $challengeToken),
    ]);
});

it('accepts each recovery code only once', function (): void {
    $enabled = enableMultiFactor($this);
    $recoveryCode = $enabled['recovery_codes'][0];

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => $enabled['user']->email,
        'password' => 'CorrectPassword1!',
        'device_name' => 'recovery-login',
    ])->assertAccepted()->json('data.challenge_token');

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'challenge_token' => $challenge,
        'code' => $recoveryCode,
    ])->assertOk();

    $secondChallenge = $this->postJson('/api/v1/auth/login', [
        'email' => $enabled['user']->email,
        'password' => 'CorrectPassword1!',
        'device_name' => 'reused-recovery-code',
    ])->assertAccepted()->json('data.challenge_token');

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'challenge_token' => $secondChallenge,
        'code' => $recoveryCode,
    ])->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'The multi-factor authentication code is invalid or has expired.',
        );

    $this->assertDatabaseHas('multi_factor_login_challenges', [
        'token_hash' => hash('sha256', (string) $secondChallenge),
        'attempts' => 1,
    ]);
    $this->assertDatabaseHas('security_events', [
        'type' => 'identity.mfa_challenge_failed',
        'subject_id' => $enabled['user']->identity_user_id,
    ]);
});

it('requires password and authenticator proof before disabling MFA', function (): void {
    $enabled = enableMultiFactor($this);

    $this->withToken($enabled['token'])->deleteJson('/api/v1/auth/mfa', [
        'current_password' => 'wrong-password',
        'code' => '123456',
    ])->assertUnauthorized();

    $this->withToken($enabled['token'])->deleteJson('/api/v1/auth/mfa', [
        'current_password' => 'CorrectPassword1!',
        'code' => '123456',
    ])->assertOk();

    $this->assertDatabaseMissing('user_multi_factor_authentication', [
        'user_id' => $enabled['user']->identity_user_id,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $enabled['user']->email,
        'password' => 'CorrectPassword1!',
        'device_name' => 'ordinary-login',
    ])->assertOk()
        ->assertJsonMissingPath('data.mfa_required');
});
