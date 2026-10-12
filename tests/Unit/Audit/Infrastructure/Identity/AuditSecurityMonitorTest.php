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

it('records privileged session grants and denials without storing credentials', function (): void {
    $userId = UserId::generate();
    $monitor = app(SecurityMonitor::class);

    $monitor->privilegedAccessGranted($userId);
    $monitor->privilegedAccessDenied($userId);

    expect(DB::table('security_events')->pluck('type')->all())
        ->toHaveCount(2)
        ->toContain(
            'identity.privileged_access_granted',
            'identity.privileged_access_denied',
        );
});

it('records customer session revocations without storing token identifiers', function (): void {
    $userId = UserId::generate();
    $monitor = app(SecurityMonitor::class);

    $monitor->sessionRevoked($userId);
    $monitor->allSessionsRevoked($userId);

    $records = DB::table('security_events')->get();

    expect($records->pluck('type')->all())
        ->toContain('identity.session_revoked', 'identity.all_sessions_revoked')
        ->and($records->pluck('subject_id')->unique()->all())->toBe([$userId->value()])
        ->and($records->pluck('details')->all())->each->toBe('[]');
});

it('records a failed MFA challenge without storing the submitted code', function (): void {
    $userId = UserId::generate();

    app(SecurityMonitor::class)->multiFactorChallengeFailed($userId);

    $this->assertDatabaseHas('security_events', [
        'type' => 'identity.mfa_challenge_failed',
        'severity' => 'warning',
        'subject_id' => $userId->value(),
        'details' => '[]',
    ]);
});
