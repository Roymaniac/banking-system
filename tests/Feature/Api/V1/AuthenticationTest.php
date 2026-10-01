<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

it('issues a Sanctum token for verified valid credentials', function (): void {
    $user = apiAuthenticationUser();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'MEMBER@example.com',
        'password' => 'correct-password',
        'device_name' => 'Test Phone',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.identity_user_id', $user->identity_user_id)
        ->assertJsonPath('data.user.email', 'member@example.com')
        ->assertJsonMissingPath('data.user.password');
    expect($response->json('data.access_token'))->toBeString()
        ->and(DB::table('personal_access_tokens')->count())->toBe(1);
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
