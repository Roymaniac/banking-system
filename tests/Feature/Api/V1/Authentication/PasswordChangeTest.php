<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\PasswordChange\ChangePassword;
use Identity\Application\PasswordChange\ChangePasswordCommand;
use Identity\Application\Security\SecurityAlertNotifier;
use Identity\Application\Security\UserSessionRevoker;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function passwordChangeUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => 'password-change@example.com',
        'email_verified_at' => now(),
        'password' => Hash::make('CurrentPassword1!'),
    ]);
}

/** @return array{current_password: string, password: string, password_confirmation: string} */
function passwordChangePayload(string $currentPassword = 'CurrentPassword1!'): array
{
    return [
        'current_password' => $currentPassword,
        'password' => 'ReplacementPassword2!',
        'password_confirmation' => 'ReplacementPassword2!',
    ];
}

it('changes the password while keeping only the current device signed in', function (): void {
    $user = passwordChangeUser();
    $currentSession = $user->createToken('current-device');
    $otherSession = $user->createToken('other-device');
    $requestId = Uuid::generate()->value();

    $this->withHeader('X-Request-ID', $requestId)
        ->withToken($currentSession->plainTextToken)
        ->postJson('/api/v1/auth/password/change', passwordChangePayload())
        ->assertOk()
        ->assertJsonPath(
            'message',
            'Password changed successfully. Other devices have been signed out.',
        );

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $currentSession->accessToken->getKey(),
    ]);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $otherSession->accessToken->getKey(),
    ]);
    $this->assertDatabaseHas('audit_log', [
        'event_name' => 'identity.user.password-changed',
        'correlation_id' => $requestId,
    ])->assertDatabaseCount('email_outbox', 1);

    // The caller can continue safely, but the removed device token cannot.
    $this->withToken($currentSession->plainTextToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    $this->app['auth']->forgetGuards();
    $this->withToken($otherSession->plainTextToken)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'CurrentPassword1!',
        'device_name' => 'old-password-test',
    ])->assertUnauthorized();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'ReplacementPassword2!',
        'device_name' => 'new-password-test',
    ])->assertOk();
});

it('rejects an incorrect current password without changing account security', function (): void {
    $user = passwordChangeUser();
    $originalPasswordHash = $user->password;
    $session = $user->createToken('current-device');

    $this->withToken($session->plainTextToken)
        ->postJson('/api/v1/auth/password/change', passwordChangePayload('WrongPassword1!'))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'The supplied credentials are invalid.');

    expect(DB::table('users')->where('id', $user->id)->value('password'))
        ->toBe($originalPasswordHash);
    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $session->accessToken->getKey(),
    ]);
});

it('requires authentication and a confirmed safe replacement password', function (): void {
    $this->postJson('/api/v1/auth/password/change', passwordChangePayload())
        ->assertUnauthorized();

    $user = passwordChangeUser();
    $session = $user->createToken('current-device');

    $this->withToken($session->plainTextToken)
        ->postJson('/api/v1/auth/password/change', [
            'current_password' => 'CurrentPassword1!',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('rolls back the password when old-session removal fails', function (): void {
    $user = passwordChangeUser();
    $originalPasswordHash = $user->password;

    $sessions = Mockery::mock(UserSessionRevoker::class);
    $sessions->shouldReceive('revokeAllExcept')
        ->once()
        ->with(Mockery::on(
            fn (UserId $userId): bool => $userId->value() === $user->identity_user_id,
        ), 42)
        ->andThrow(new RuntimeException('Simulated session revocation failure.'));
    app()->instance(UserSessionRevoker::class, $sessions);

    expect(fn () => app(ChangePassword::class)->handle(new ChangePasswordCommand(
        new UserId((string) $user->identity_user_id),
        'CurrentPassword1!',
        'ReplacementPassword2!',
        42,
    )))->toThrow(RuntimeException::class, 'Simulated session revocation failure.');

    expect(DB::table('users')->where('id', $user->id)->value('password'))
        ->toBe($originalPasswordHash);
});

it('rolls back the security change when its durable alert cannot be recorded', function (): void {
    $user = passwordChangeUser();
    $originalPasswordHash = $user->password;
    $session = $user->createToken('current-device');
    $otherSession = $user->createToken('other-device');

    $alerts = Mockery::mock(SecurityAlertNotifier::class);
    $alerts->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Simulated security alert storage failure.'));
    app()->instance(SecurityAlertNotifier::class, $alerts);

    $this->withoutExceptionHandling();

    expect(fn () => $this->withToken($session->plainTextToken)
        ->postJson('/api/v1/auth/password/change', passwordChangePayload()))
        ->toThrow(RuntimeException::class, 'Simulated security alert storage failure.');

    expect(DB::table('users')->where('id', $user->id)->value('password'))
        ->toBe($originalPasswordHash);
    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $otherSession->accessToken->getKey(),
    ])->assertDatabaseCount('email_outbox', 0);
});
