<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function sessionManagementUser(string $email = 'sessions@example.com'): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => $email,
        'email_verified_at' => now(),
    ]);
}

it('lists a bounded page of safe session details and marks the current device', function (): void {
    $user = sessionManagementUser();
    $first = $user->createToken('first-device', ['customer']);
    $current = $user->createToken('current-device', ['customer']);
    $third = $user->createToken('third-device', ['customer']);

    $response = $this->withToken($current->plainTextToken)
        ->getJson('/api/v1/auth/sessions?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.has_next_page', true)
        ->assertJsonCount(2, 'data.sessions')
        ->assertJsonMissingPath('data.sessions.0.token')
        ->assertJsonMissingPath('data.sessions.0.tokenable_id');

    $listedSessions = collect($response->json('data.sessions'));
    expect($listedSessions->pluck('id')->all())
        ->toBe([$third->accessToken->getKey(), $current->accessToken->getKey()])
        ->and($listedSessions->firstWhere('id', $current->accessToken->getKey())['is_current'])
        ->toBeTrue()
        ->and(json_encode($response->json(), JSON_THROW_ON_ERROR))
        ->not->toContain((string) DB::table('personal_access_tokens')
        ->where('id', $first->accessToken->getKey())
        ->value('token'));
});

it('revokes one owned device without ending the current session', function (): void {
    $user = sessionManagementUser();
    $current = $user->createToken('current-device');
    $other = $user->createToken('old-phone');

    $this->withToken($current->plainTextToken)
        ->deleteJson('/api/v1/auth/sessions/'.$other->accessToken->getKey())
        ->assertOk()
        ->assertJsonPath('message', 'Session signed out successfully.');

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $current->accessToken->getKey(),
    ]);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $other->accessToken->getKey(),
    ]);
    $this->assertDatabaseHas('security_events', [
        'type' => 'identity.session_revoked',
        'subject_id' => $user->identity_user_id,
    ]);

    $this->withToken($current->plainTextToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

it('does not reveal or revoke a session owned by another user', function (): void {
    $user = sessionManagementUser();
    $current = $user->createToken('current-device');
    $otherUser = sessionManagementUser('other-sessions@example.com');
    $foreign = $otherUser->createToken('foreign-device');

    $this->withToken($current->plainTextToken)
        ->deleteJson('/api/v1/auth/sessions/'.$foreign->accessToken->getKey())
        ->assertNotFound()
        ->assertJsonPath('message', 'Session not found.');

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $foreign->accessToken->getKey(),
    ]);
    $this->assertDatabaseMissing('security_events', [
        'type' => 'identity.session_revoked',
    ]);
});

it('signs out every device including the session making the request', function (): void {
    $user = sessionManagementUser();
    $current = $user->createToken('current-device');
    $user->createToken('other-device');

    $this->withToken($current->plainTextToken)
        ->deleteJson('/api/v1/auth/sessions')
        ->assertOk()
        ->assertJsonPath('message', 'Signed out on every device.');

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
    $this->assertDatabaseHas('security_events', [
        'type' => 'identity.all_sessions_revoked',
        'subject_id' => $user->identity_user_id,
    ]);

    $this->app['auth']->forgetGuards();
    $this->withToken($current->plainTextToken)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('protects session management and validates bounded pagination', function (): void {
    $this->getJson('/api/v1/auth/sessions')->assertUnauthorized();
    $this->deleteJson('/api/v1/auth/sessions')->assertUnauthorized();

    $user = sessionManagementUser();
    $current = $user->createToken('current-device');

    $this->withToken($current->plainTextToken)
        ->getJson('/api/v1/auth/sessions?page=0&per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page', 'per_page']);
});
