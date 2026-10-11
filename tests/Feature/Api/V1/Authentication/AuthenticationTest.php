<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function apiAuthenticationUser(bool $verified = true): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => 'member@example.com',
        'email_verified_at' => $verified ? now() : null,
        'password' => 'correct-password',
    ]);
}

function makeApiAuthenticationStaff(User $user, string $status = 'active'): void
{
    $departmentId = Uuid::generate()->value();

    DB::table('departments')->insert([
        'id' => $departmentId,
        'code' => 'SEC-'.substr($departmentId, 0, 6),
        'name' => 'Security Operations',
        'status' => 'active',
        'created_at' => now(),
        'version' => 1,
    ]);
    DB::table('staff')->insert([
        'id' => Uuid::generate()->value(),
        'user_id' => $user->identity_user_id,
        'employee_number' => 'EMP-'.substr($departmentId, 0, 8),
        'department_id' => $departmentId,
        'job_title' => 'Security Operator',
        'status' => $status,
        'hired_at' => now(),
        'deactivated_at' => $status === 'active' ? null : now(),
        'version' => 1,
    ]);
}

it('issues a Sanctum token for verified valid credentials', function (): void {
    $user = apiAuthenticationUser();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'MEMBER@example.com',
        'password' => 'correct-password',
        'device_name' => 'Test Phone',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure(['data' => ['access_token', 'expires_at', 'user']])
        ->assertJsonPath('data.user.identity_user_id', $user->identity_user_id)
        ->assertJsonPath('data.user.email', 'member@example.com')
        ->assertJsonMissingPath('data.user.password');
    expect($response->json('data.access_token'))->toBeString()
        ->and(DB::table('personal_access_tokens')->count())->toBe(1)
        ->and(json_decode(
            (string) DB::table('personal_access_tokens')->value('abilities'),
            true,
            flags: JSON_THROW_ON_ERROR,
        ))->toBe(['customer']);
});

it('returns the current identity and revokes the current token on logout', function (): void {
    $user = apiAuthenticationUser();
    $token = $user->createToken('Test Phone')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.identity_user_id', $user->identity_user_id);
    $this->withToken($token)->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Signed out successfully.');

    expect(DB::table('personal_access_tokens')->count())->toBe(0);

    // Feature tests reuse one application instance, so clear the user remembered
    // by Laravel's auth guard before simulating a completely new API request.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('uses a generic unauthorized response for invalid credentials', function (): void {
    apiAuthenticationUser();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Test Phone',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'The supplied credentials are invalid.');
});

it('refuses login until the identity email is verified', function (): void {
    apiAuthenticationUser(false);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'correct-password',
        'device_name' => 'Test Phone',
    ])->assertForbidden()
        ->assertJsonPath('message', 'The email address must be verified before signing in.');
});

it('validates login input without echoing a password', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
        'password' => '',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name'])
        ->assertDontSee('not-an-email');
});

it('requires an active staff member to confirm their password for privileged access', function (): void {
    $user = apiAuthenticationUser();
    makeApiAuthenticationStaff($user);
    $ordinaryToken = $user->createToken('Ordinary browser', ['customer'])->plainTextToken;

    Route::middleware(['auth:sanctum', 'privileged-token'])
        ->get('/api/testing/privileged-access', static fn () => response()->json(['allowed' => true]));

    $this->withToken($ordinaryToken)->getJson('/api/testing/privileged-access')
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'A recent privileged staff sign-in is required for this action.',
        );

    $response = $this->withToken($ordinaryToken)->postJson('/api/v1/auth/privileged-access', [
        'password' => 'correct-password',
        'device_name' => 'Operations workstation',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);

    $privilegedToken = (string) $response->json('data.access_token');
    $this->app['auth']->forgetGuards();
    $this->withToken($privilegedToken)->getJson('/api/testing/privileged-access')
        ->assertOk()
        ->assertJsonPath('allowed', true);

    $storedToken = DB::table('personal_access_tokens')->latest('id')->first();
    expect(json_decode((string) $storedToken->abilities, true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['customer', 'privileged'])
        ->and($storedToken->expires_at)->not->toBeNull();
    $this->assertDatabaseHas('security_events', [
        'type' => 'identity.privileged_access_granted',
        'subject_id' => $user->identity_user_id,
    ]);
});

it('rejects privileged access for incorrect credentials and inactive staff', function (): void {
    $user = apiAuthenticationUser();
    makeApiAuthenticationStaff($user, 'inactive');
    $ordinaryToken = $user->createToken('Ordinary browser', ['customer'])->plainTextToken;
    $url = '/api/v1/auth/privileged-access';

    $this->withToken($ordinaryToken)->postJson($url, [
        'password' => 'wrong-password',
        'device_name' => 'Operations workstation',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'The supplied credentials are invalid.');

    $this->withToken($ordinaryToken)->postJson($url, [
        'password' => 'correct-password',
        'device_name' => 'Operations workstation',
    ])->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');

    expect(DB::table('personal_access_tokens')->count())->toBe(1);
    $this->assertDatabaseHas('security_events', [
        'type' => 'identity.privileged_access_denied',
        'subject_id' => $user->identity_user_id,
    ]);
});

it('rejects an expired privileged token', function (): void {
    $user = apiAuthenticationUser();
    $expiredToken = $user->createToken(
        'Expired privileged session',
        ['customer', 'privileged'],
        now()->subMinute(),
    )->plainTextToken;

    Route::middleware(['auth:sanctum', 'privileged-token'])
        ->get('/api/testing/expired-privileged-access', static fn () => response()->json(['allowed' => true]));

    $this->withToken($expiredToken)->getJson('/api/testing/expired-privileged-access')
        ->assertUnauthorized();
});
