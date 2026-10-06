<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function apiDocumentationUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowApiDocumentation(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return $permission->value() === 'documentation.view';
        }
    });
}

function apiDocumentationToken(User $user): string
{
    return $user->createToken('api-documentation-test')->plainTextToken;
}

it('protects API documentation outside the local environment', function (): void {
    $this->get('/docs/api')->assertForbidden();
    $this->get('/docs/api.json')->assertForbidden();

    $this->actingAs(apiDocumentationUser());

    $this->get('/docs/api.json')->assertForbidden();
});

it('serves documentation to an authorized user', function (): void {
    allowApiDocumentation();
    $token = apiDocumentationToken(apiDocumentationUser());

    $this->withToken($token)->get('/docs/api')
        ->assertOk()
        ->assertSee('Banking System API');
});

it('generates a versioned OpenAPI contract with bearer authentication', function (): void {
    allowApiDocumentation();
    $token = apiDocumentationToken(apiDocumentationUser());

    $document = $this->withToken($token)->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('info.title', 'Banking System API')
        ->assertJsonPath('info.version', '1.0.0')
        ->assertJsonPath('servers.0.url', 'http://localhost/api/v1')
        ->assertJsonPath('components.securitySchemes.http.type', 'http')
        ->assertJsonPath('components.securitySchemes.http.scheme', 'bearer')
        ->json();

    expect($document['paths'])
        ->toHaveKeys([
            '/auth/login',
            '/auth/me',
            '/accounts',
            '/operations/health',
            '/reports/ledger',
            '/audit/domain-events',
            '/notifications/outbox',
        ])
        ->not->toHaveKey('/up')
        ->not->toHaveKey('/docs/api.json')
        ->and($document['paths']['/auth/login']['post']['security'])->toBe([])
        ->and($document['security'])->toBe([['http' => []]]);
});
