<?php

declare(strict_types=1);

use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Transaction\Application\Control\BreakGlassMoneyMovementResume;
use Transaction\Application\Control\Exception\MoneyMovementSuspended;
use Transaction\Application\Control\MoneyMovementControl;
use Transaction\Application\Control\MoneyMovementSecurityMonitor;
use Transaction\Infrastructure\Control\ControlledMoneyMovementTransactionManager;

uses(RefreshDatabase::class);

it('starts enabled and blocks controlled transactions after suspension', function (): void {
    $ran = false;
    app(ControlledMoneyMovementTransactionManager::class)->run(function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();

    app(MoneyMovementControl::class)->suspend('Investigating an accounting integrity alert.', 'test');

    app(ControlledMoneyMovementTransactionManager::class)->run(fn () => null);
})->throws(MoneyMovementSuspended::class, 'Money movement is temporarily suspended.');

it('suspends and resumes through auditable operator commands', function (): void {
    $this->artisan('banking:money-movement:suspend', [
        'reason' => 'Emergency investigation is now in progress.',
    ])->assertSuccessful();

    $this->assertDatabaseHas('money_movement_controls', [
        'name' => 'global',
        'enabled' => false,
        'source' => 'operator_cli',
    ]);
    $this->assertDatabaseHas('money_movement_control_events', [
        'action' => 'suspended',
        'reason' => 'Emergency investigation is now in progress.',
        'source' => 'operator_cli',
    ]);

    $this->artisan('banking:money-movement:resume', [
        'reason' => 'Investigation completed and approval was received.',
    ])->assertSuccessful();

    $this->assertDatabaseHas('money_movement_controls', [
        'name' => 'global',
        'enabled' => true,
        'reason' => null,
        'source' => 'operator_cli',
    ]);
    $this->assertDatabaseHas('money_movement_control_events', [
        'action' => 'resumed',
        'reason' => 'Investigation completed and approval was received.',
        'source' => 'operator_cli',
    ]);
});

it('does not create duplicate audit events when the requested state is unchanged', function (): void {
    $control = app(MoneyMovementControl::class);
    $reason = 'Investigation remains active for this incident.';
    $control->suspend($reason, 'test');
    $control->suspend($reason, 'test');

    $this->assertDatabaseCount('money_movement_control_events', 1);
});

it('advances the control revision when a new incident is reported during suspension', function (): void {
    $control = app(MoneyMovementControl::class);
    $control->suspend('Investigating the original reconciliation incident.', 'test');
    $firstRevision = $control->current()->revision;

    $control->suspend('A second reconciliation incident now requires review.', 'test');

    expect($control->current()->revision)->toBe($firstRevision + 1);
    $this->assertDatabaseCount('money_movement_control_events', 2);
});

it('rejects an operational reason that is too short', function (): void {
    $this->artisan('banking:money-movement:suspend', ['reason' => 'short'])
        ->expectsOutputToContain('between 10 and 255 characters')
        ->assertFailed();

    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => true]);
    $this->assertDatabaseCount('money_movement_control_events', 0);
});

it('blocks the ordinary resume command in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    app(MoneyMovementControl::class)->suspend('Production incident investigation remains active.', 'test');

    $this->artisan('banking:money-movement:resume', [
        'reason' => 'Attempting to bypass the protected approval workflow.',
    ])->expectsOutputToContain('Direct production resumption is disabled')
        ->assertFailed();

    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => false]);
});

it('records an authorized production break-glass resume as a critical security event', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    $operatorId = UserId::generate();
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return $permission->value() === 'money_movement.break_glass';
        }
    });
    app(MoneyMovementControl::class)->suspend('Production recovery requires emergency intervention.', 'test');

    $this->artisan('banking:money-movement:resume', [
        'reason' => 'Primary approval tooling is unavailable during recovery.',
        '--break-glass' => true,
        '--operator' => $operatorId->value(),
        '--incident' => 'inc-9001',
        '--yes' => true,
    ])->expectsOutputToContain('BREAK-GLASS USED')
        ->assertSuccessful();

    $this->assertDatabaseHas('money_movement_controls', [
        'name' => 'global',
        'enabled' => true,
        'source' => 'operator_cli_break_glass',
    ]);
    $this->assertDatabaseHas('money_movement_control_events', [
        'action' => 'resumed',
        'actor_user_id' => $operatorId->value(),
        'reason' => 'INC-9001: Primary approval tooling is unavailable during recovery.',
    ]);

    $securityEvent = DB::table('security_events')->sole();
    expect($securityEvent->type)->toBe('operations.money_movement_break_glass_resumed')
        ->and($securityEvent->severity)->toBe('critical')
        ->and($securityEvent->subject_id)->toBe($operatorId->value())
        ->and(json_decode((string) $securityEvent->details, true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray(['incident_reference' => 'INC-9001', 'channel' => 'cli']);
});

it('rejects break-glass use by an operator without its dedicated permission', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return false;
        }
    });
    app(MoneyMovementControl::class)->suspend('Production incident remains under investigation.', 'test');

    $this->artisan('banking:money-movement:resume', [
        'reason' => 'Unauthorized emergency recovery should not be accepted.',
        '--break-glass' => true,
        '--operator' => UserId::generate()->value(),
        '--incident' => 'INC-9002',
        '--yes' => true,
    ])->expectsOutputToContain('does not have the money_movement.break_glass permission')
        ->assertFailed();

    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => false]);
    $this->assertDatabaseCount('security_events', 0);
});

it('rolls back emergency resumption when its security record cannot be stored', function (): void {
    $operatorId = UserId::generate();
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
    app()->instance(MoneyMovementSecurityMonitor::class, new class implements MoneyMovementSecurityMonitor
    {
        public function breakGlassResumeUsed(UserId $operatorId, string $incidentReference): void
        {
            throw new RuntimeException('Security storage unavailable.');
        }
    });
    app(MoneyMovementControl::class)->suspend('Emergency rollback behavior is under test.', 'test');

    expect(fn () => app(BreakGlassMoneyMovementResume::class)->handle(
        $operatorId,
        'INC-9003',
        'Approval tooling is unavailable during this controlled test.',
    ))->toThrow(RuntimeException::class, 'Security storage unavailable.');

    $this->assertDatabaseHas('money_movement_controls', ['name' => 'global', 'enabled' => false]);
    $this->assertDatabaseMissing('money_movement_control_events', ['action' => 'resumed']);
});
