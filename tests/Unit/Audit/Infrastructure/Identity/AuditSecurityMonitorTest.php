<?php

declare(strict_types=1);

use Audit\Domain\Security\Repository\SecurityEventRepository;
use Audit\Infrastructure\Identity\AuditSecurityMonitor;
use Audit\Infrastructure\Persistence\DatabaseSecurityEventRepository;
use Identity\Application\Security\SecurityMonitor;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds Identity security monitoring to durable audit storage', function (): void {
    expect(app(SecurityMonitor::class))->toBeInstanceOf(AuditSecurityMonitor::class)
        ->and(app(SecurityEventRepository::class))->toBeInstanceOf(DatabaseSecurityEventRepository::class);
});

it('records a successful login against the known user', function (): void {
    $userId = UserId::generate();

    app(SecurityMonitor::class)->loginSucceeded($userId);

    $record = DB::table('security_events')->first();

    expect($record->type)->toBe('identity.login_succeeded')
        ->and($record->severity)->toBe('information')
        ->and($record->subject_id)->toBe($userId->value())
        ->and($record->subject_fingerprint)->toBeNull();
});

it('fingerprints a failed login email instead of storing the address', function (): void {
    app(SecurityMonitor::class)->loginFailed('Private.User@Example.com');

    $record = DB::table('security_events')->first();

    expect($record->type)->toBe('identity.login_failed')
        ->and($record->severity)->toBe('warning')
        ->and($record->subject_id)->toBeNull()
        ->and($record->subject_fingerprint)->toBe(hash('sha256', 'private.user@example.com'))
        ->and(json_encode($record, JSON_THROW_ON_ERROR))->not->toContain('Private.User@Example.com');
});

it('records denied permissions without storing request input', function (): void {
    $request = Request::create('/transfers/approve', 'POST', ['password' => 'never-store-this']);
    $request->headers->set('User-Agent', 'Security Test Client');
    $this->app->instance('request', $request);
    $monitor = $this->app->make(SecurityMonitor::class);

    $monitor->accessDenied(UserId::generate(), new Permission('transfers.approve'));

    $record = DB::table('security_events')->first();

    expect($record->type)->toBe('identity.access_denied')
        ->and($record->user_agent)->toBe('Security Test Client')
        ->and(json_decode($record->details, true, 512, JSON_THROW_ON_ERROR))
        ->toBe(['permission' => 'transfers.approve'])
        ->and(json_encode($record, JSON_THROW_ON_ERROR))->not->toContain('never-store-this');
});
