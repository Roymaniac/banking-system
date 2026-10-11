<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\EmailVerification\EmailVerificationNotifier;
use Identity\Application\EmailVerification\EmailVerificationTokenGenerator;
use Identity\Application\Registration\RegisterUser;
use Identity\Application\Registration\RegisterUserCommand;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function useRegistrationToken(string $token): void
{
    app()->instance(EmailVerificationTokenGenerator::class, new class($token) implements EmailVerificationTokenGenerator
    {
        public function __construct(private readonly string $token) {}

        public function generate(): EmailVerificationToken
        {
            return new EmailVerificationToken($this->token);
        }
    });
}

/** @return array{email: string, password: string, password_confirmation: string} */
function registrationPayload(string $email = 'new-customer@example.com'): array
{
    return [
        'email' => $email,
        'password' => 'CorrectHorseBattery1!',
        'password_confirmation' => 'CorrectHorseBattery1!',
    ];
}

it('registers, queues verification safely, verifies, and then permits login', function (): void {
    $token = str_repeat('a', 64);
    $requestId = Uuid::generate()->value();
    useRegistrationToken($token);

    $this->withHeader('X-Request-ID', $requestId)
        ->postJson('/api/v1/auth/register', registrationPayload(' New-Customer@Example.com '))
        ->assertCreated()
        ->assertJsonPath(
            'message',
            'Registration successful. Check your email to verify the account.',
        );

    $user = DB::table('users')->where('email', 'new-customer@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->identity_user_id)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    $this->assertDatabaseHas('email_verification_tokens', [
        'user_id' => $user->identity_user_id,
        'token' => hash('sha256', $token),
    ]);

    $outbox = DB::table('email_outbox')->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->encrypted_payload)->not->toContain($token)
        ->and($outbox->encrypted_payload)->not->toContain('new-customer@example.com');

    $this->assertDatabaseHas('audit_log', [
        'event_name' => 'identity.user.registered',
        'correlation_id' => $requestId,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'new-customer@example.com',
        'password' => 'CorrectHorseBattery1!',
        'device_name' => 'registration-test',
    ])->assertForbidden();

    $verifyRequestId = Uuid::generate()->value();

    $this->withHeader('X-Request-ID', $verifyRequestId)
        ->postJson('/api/v1/auth/email/verify', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('message', 'Email verified successfully.');

    $this->assertDatabaseMissing('email_verification_tokens', ['token' => hash('sha256', $token)]);

    $this->assertDatabaseHas('audit_log', [
        'event_name' => 'identity.user.email-verified',
        'correlation_id' => $verifyRequestId,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'new-customer@example.com',
        'password' => 'CorrectHorseBattery1!',
        'device_name' => 'registration-test',
    ])->assertOk()
        ->assertJsonPath('data.user.email_verified', true);
});

it('rejects duplicate registration and unsafe credentials', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/api/v1/auth/register', registrationPayload('existing@example.com'))
        ->assertConflict()
        ->assertJsonPath('message', 'An account already uses this email address.');
    $this->postJson('/api/v1/auth/register', [
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('resends verification without revealing account existence', function (): void {
    $token = str_repeat('b', 64);
    useRegistrationToken($token);

    $user = User::factory()->unverified()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => 'unverified@example.com',
    ]);

    $known = $this->postJson('/api/v1/auth/email/verification', [
        'email' => $user->email,
    ])->assertAccepted()->json('message');

    $unknown = $this->postJson('/api/v1/auth/email/verification', [
        'email' => 'unknown@example.com',
    ])->assertAccepted()->json('message');

    expect($unknown)->toBe($known);

    $this->assertDatabaseHas('email_verification_tokens', [
        'user_id' => $user->identity_user_id,
        'token' => hash('sha256', $token),
    ]);
    expect(DB::table('email_verification_tokens')->count())->toBe(1);
});

it('rolls back registration when verification delivery cannot be recorded', function (): void {
    $notifier = Mockery::mock(EmailVerificationNotifier::class);
    $notifier->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Simulated verification delivery failure.'));
    app()->instance(EmailVerificationNotifier::class, $notifier);

    expect(fn () => app(RegisterUser::class)->handle(new RegisterUserCommand(
        'rollback-registration@example.com',
        'CorrectHorseBattery1!',
    )))->toThrow(RuntimeException::class, 'Simulated verification delivery failure.');

    $this->assertDatabaseMissing('users', ['email' => 'rollback-registration@example.com']);

    expect(DB::table('email_verification_tokens')->count())->toBe(0);
});

it('returns one safe error for malformed and expired verification tokens', function (): void {
    $this->postJson('/api/v1/auth/email/verify', ['token' => 'malformed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');

    $this->postJson('/api/v1/auth/email/verify', ['token' => str_repeat('c', 64)])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The email-verification token is invalid or has expired.');
});
