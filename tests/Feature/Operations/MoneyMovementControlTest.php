<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Transaction\Application\Control\Exception\MoneyMovementSuspended;
use Transaction\Application\Control\MoneyMovementControl;
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
