<?php

declare(strict_types=1);

use Account\Application\Closure\AccountClosureBalanceChecker;
use Account\Domain\Account\Exception\NonZeroAccountBalance;
use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Infrastructure\Balance\ProjectedAccountClosureBalanceChecker;
use Tests\TestCase;

uses(TestCase::class);

it('binds the account closure checker to the ledger-backed implementation', function (): void {
    expect(app(AccountClosureBalanceChecker::class))
        ->toBeInstanceOf(ProjectedAccountClosureBalanceChecker::class);
});

it('rejects closure when the projected balance is not zero', function (): void {
    $accountId = AccountId::generate();
    $ledger = Ledger::reconstitute(
        LedgerId::generate(),
        $accountId,
        new LedgerCurrency('NGN'),
        new DateTimeImmutable,
        1,
    );
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->once()->with($accountId)->andReturn($ledger);
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('find')->once()->with($ledger->id())->andReturn(
        new LedgerBalance($ledger->id(), $ledger->currency(), 0, 5000),
    );

    (new ProjectedAccountClosureBalanceChecker($ledgers, $balances))
        ->assertZeroBalance($accountId);
})->throws(NonZeroAccountBalance::class, 'An account must have a zero balance');

it('allows closure when debits and credits leave a zero balance', function (): void {
    $accountId = AccountId::generate();
    $ledger = Ledger::reconstitute(
        LedgerId::generate(),
        $accountId,
        new LedgerCurrency('NGN'),
        new DateTimeImmutable,
        1,
    );
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->once()->andReturn($ledger);
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('find')->once()->andReturn(
        new LedgerBalance($ledger->id(), $ledger->currency(), 5000, 5000),
    );

    (new ProjectedAccountClosureBalanceChecker($ledgers, $balances))
        ->assertZeroBalance($accountId);

    expect(true)->toBeTrue();
});
