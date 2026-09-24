<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Application\Withdrawal\MakeWithdrawal;
use Transaction\Application\Withdrawal\MakeWithdrawalCommand;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;
use Transaction\Domain\Withdrawal\Event\WithdrawalCompleted;
use Transaction\Domain\Withdrawal\Exception\InsufficientFunds;
use Transaction\Domain\Withdrawal\Repository\WithdrawalRepository;
use Transaction\Domain\Withdrawal\Withdrawal;

final readonly class MakeWithdrawalTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-21T10:00:00+01:00');
    }
}

final class MakeWithdrawalTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class MakeWithdrawalTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function withdrawalTestAccount(): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        3,
        new AccountNumber('1234567890'),
        AccountStatus::Active
    );
}

function withdrawalTestLedger(AccountId $accountId): Ledger
{
    return Ledger::reconstitute(
        LedgerId::generate(),
        $accountId,
        new LedgerCurrency('NGN'),
        new DateTimeImmutable('2026-09-19T10:00:00+01:00'),
        1
    );
}

it('locks the balance and atomically completes a withdrawal', function (): void {
    $account = withdrawalTestAccount();
    $customerLedger = withdrawalTestLedger($account->id());
    $disbursementLedger = withdrawalTestLedger(AccountId::generate());
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->andReturn($account);
    $accounts->shouldReceive('findByIdForUpdate')->once()->with($account->id())->andReturn($account);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->once()->andReturn($customerLedger);
    $ledgers->shouldReceive('findById')->once()->andReturn($disbursementLedger);
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('save')->once()->with(Mockery::type(LedgerEntry::class));
    $withdrawals = Mockery::mock(WithdrawalRepository::class);
    $withdrawals->shouldReceive('referenceExists')->once()->with(Mockery::type(TransactionReference::class))->andReturnFalse();
    $withdrawals->shouldReceive('save')->once()->with(Mockery::type(Withdrawal::class));
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('findForUpdate')->once()->with($customerLedger->id())
        ->andReturn(new LedgerBalance($customerLedger->id(), $customerLedger->currency(), 0, 50000));

    $balances->shouldReceive('apply')->once()->with(Mockery::type(LedgerEntry::class), Mockery::type(DateTimeImmutable::class));
    $limits = Mockery::mock(DailyTransactionLimitRepository::class);
    $limits->shouldReceive('consume')->once()->with($account->id(), $customerLedger->currency(), 20000, Mockery::type(DateTimeImmutable::class));
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->times(9)->andReturn(...array_map(fn(): Uuid => Uuid::generate(), range(1, 9)));
    $publisher = new MakeWithdrawalTestPublisher;

    $withdrawal = (new MakeWithdrawal(
        $accounts,
        $ledgers,
        $entries,
        $withdrawals,
        $balances,
        $limits,
        new MakeWithdrawalTestClock,
        $ids,
        new MakeWithdrawalTestTransactions,
        $publisher
    ))->handle(new MakeWithdrawalCommand(
        $account->id(),
        $disbursementLedger->id(),
        20000,
        'atm-1001',
        new DateTimeImmutable('2026-09-21T09:55:00+01:00')
    ));

    expect($withdrawal->amount()->minorUnits())->toBe(20000)
        ->and($publisher->published)->toHaveCount(5)
        ->and($publisher->published[4])->toBeInstanceOf(WithdrawalCompleted::class);
});

it('rejects a withdrawal when the locked balance is too low', function (): void {
    $account = withdrawalTestAccount();
    $customerLedger = withdrawalTestLedger($account->id());
    $disbursementLedger = withdrawalTestLedger(AccountId::generate());
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->andReturn($account);
    $accounts->shouldReceive('findByIdForUpdate')->once()->with($account->id())->andReturn($account);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->once()->andReturn($customerLedger);
    $ledgers->shouldReceive('findById')->once()->andReturn($disbursementLedger);
    $withdrawals = Mockery::mock(WithdrawalRepository::class);
    $withdrawals->shouldReceive('referenceExists')->once()->andReturnFalse();
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('findForUpdate')->once()->andReturn(new LedgerBalance($customerLedger->id(), $customerLedger->currency(), 0, 500));

    (new MakeWithdrawal(
        $accounts,
        $ledgers,
        Mockery::mock(LedgerEntryRepository::class),
        $withdrawals,
        $balances,
        Mockery::mock(DailyTransactionLimitRepository::class),
        new MakeWithdrawalTestClock,
        Mockery::mock(UuidGenerator::class),
        new MakeWithdrawalTestTransactions,
        new MakeWithdrawalTestPublisher
    ))->handle(new MakeWithdrawalCommand(
        $account->id(),
        $disbursementLedger->id(),
        1000,
        'atm-1002',
        new DateTimeImmutable('2026-09-21T09:55:00+01:00')
    ));
})->throws(InsufficientFunds::class, 'does not have enough');
