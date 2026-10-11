<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\PasswordReset\PasswordResetTokenGenerator;
use Identity\Application\PasswordReset\ResetPassword;
use Identity\Application\PasswordReset\ResetPasswordCommand;
use Identity\Application\Security\UserSessionRevoker;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function usePasswordRecoveryToken(string $token): void
{
    app()->instance(PasswordResetTokenGenerator::class, new class($token) implements PasswordResetTokenGenerator
    {
        public function __construct(private readonly string $token) {}

        public function generate(): PasswordResetToken
        {
            return new PasswordResetToken($this->token);
        }
    });
}

function passwordRecoveryUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => 'recovery-customer@example.com',
        'email_verified_at' => now(),
        'password' => Hash::make('OldSecurePassword1!'),
    ]);
}

/** @return array{token: string, password: string, password_confirmation: string} */
function passwordRecoveryPayload(string $token): array
{
    return [
        'token' => $token,
        'password' => 'NewSecurePassword2!',
        'password_confirmation' => 'NewSecurePassword2!',
    ];
}

it('requests recovery without storing the raw token or email message', function (): void {
    $token = str_repeat('a', 64);
    $user = passwordRecoveryUser();
    usePasswordRecoveryToken($token);

    $this->postJson('/api/v1/auth/password/forgot', ['email' => strtoupper($user->email)])
        ->assertAccepted()
        ->assertJsonPath(
            'message',
            'If the email belongs to an account, password-reset instructions have been sent.',
        );

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => $user->email,
        'token' => hash('sha256', $token),
    ]);

    $outbox = DB::table('email_outbox')->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->encrypted_payload)->not->toContain($token)
        ->and($outbox->encrypted_payload)->not->toContain($user->email);
});

it('does not reveal whether a recovery email is registered', function (): void {
    $token = str_repeat('b', 64);
    $user = passwordRecoveryUser();
    usePasswordRecoveryToken($token);

    $knownMessage = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => $user->email,
    ])->assertAccepted()->json('message');

    $unknownMessage = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'unknown-customer@example.com',
    ])->assertAccepted()->json('message');

    expect($unknownMessage)->toBe($knownMessage)
        ->and(DB::table('password_reset_tokens')->count())->toBe(1)
        ->and(DB::table('email_outbox')->count())->toBe(1);
});

it('resets the password, consumes the token, and signs out every device', function (): void {
    $token = str_repeat('c', 64);
    $requestId = Uuid::generate()->value();
    $user = passwordRecoveryUser();
    usePasswordRecoveryToken($token);

    $user->createToken('phone');
    $user->createToken('laptop');
    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertAccepted();

    expect(DB::table('personal_access_tokens')->count())->toBe(2);

    $this->withHeader('X-Request-ID', $requestId)
        ->postJson('/api/v1/auth/password/reset', passwordRecoveryPayload($token))
        ->assertOk()
        ->assertJsonPath(
            'message',
            'Password reset successfully. Sign in again.',
        );

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
    $this->assertDatabaseMissing('password_reset_tokens', ['token' => hash('sha256', $token)]);
    $this->assertDatabaseHas('audit_log', [
        'event_name' => 'identity.user.password-reset',
        'correlation_id' => $requestId,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'OldSecurePassword1!',
        'device_name' => 'old-password-test',
    ])->assertUnauthorized();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'NewSecurePassword2!',
        'device_name' => 'new-password-test',
    ])->assertOk();
});

it('returns safe validation errors for unusable reset requests', function (): void {
    $this->postJson('/api/v1/auth/password/reset', [
        'token' => 'malformed',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'password']);

    $this->postJson('/api/v1/auth/password/reset', passwordRecoveryPayload(str_repeat('d', 64)))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The password-reset token is invalid or has expired.');
});

it('rolls back all reset writes when session revocation fails', function (): void {
    $token = str_repeat('e', 64);
    $user = passwordRecoveryUser();
    $originalPasswordHash = $user->password;
    usePasswordRecoveryToken($token);

    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertAccepted();

    $sessions = Mockery::mock(UserSessionRevoker::class);
    $sessions->shouldReceive('revokeAll')
        ->once()
        ->andThrow(new RuntimeException('Simulated session revocation failure.'));
    app()->instance(UserSessionRevoker::class, $sessions);

    expect(fn () => app(ResetPassword::class)->handle(new ResetPasswordCommand(
        $token,
        'NewSecurePassword2!',
    )))->toThrow(RuntimeException::class, 'Simulated session revocation failure.');

    expect(DB::table('users')->where('id', $user->id)->value('password'))
        ->toBe($originalPasswordHash);
    $this->assertDatabaseHas('password_reset_tokens', ['token' => hash('sha256', $token)]);
});
