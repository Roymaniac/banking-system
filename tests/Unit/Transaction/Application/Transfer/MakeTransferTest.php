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
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Application\Transfer\MakeTransfer;
use Transaction\Application\Transfer\MakeTransferCommand;
use Transaction\Domain\Transfer\Event\TransferCompleted;
use Transaction\Domain\Transfer\Exception\InsufficientTransferFunds;
use Transaction\Domain\Transfer\Exception\SameAccountTransfer;
use Transaction\Domain\Transfer\Repository\TransferRepository;
use Transaction\Domain\Transfer\Transfer;

final readonly class MakeTransferTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-21T12:00:00+01:00');
    }
}

final class MakeTransferTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class MakeTransferTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function transferTestAccount(string $number): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        3,
        new AccountNumber($number),
        AccountStatus::Active
    );
}

function transferTestLedger(AccountId $accountId): Ledger
{
    return Ledger::reconstitute(
        LedgerId::generate(),
        $accountId,
        new LedgerCurrency('NGN'),
        new DateTimeImmutable('2026-09-19T10:00:00+01:00'),
        1
    );
}

it('atomically debits the sender and credits the recipient', function (): void {
    $sender = transferTestAccount('1234567890');
    $recipient = transferTestAccount('1987654321');
    $senderLedger = transferTestLedger($sender->id());
    $recipientLedger = transferTestLedger($recipient->id());
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->with($sender->id())->andReturn($sender);
    $accounts->shouldReceive('findById')->once()->with($recipient->id())->andReturn($recipient);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->once()->with($sender->id())->andReturn($senderLedger);
    $ledgers->shouldReceive('findByAccountId')->once()->with($recipient->id())->andReturn($recipientLedger);
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('save')->once()->with(Mockery::type(LedgerEntry::class));
    $transfers = Mockery::mock(TransferRepository::class);
    $transfers->shouldReceive('referenceExists')->once()->andReturnFalse();
    $transfers->shouldReceive('save')->once()->with(Mockery::type(Transfer::class));
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('findForUpdate')->once()->with($senderLedger->id())->andReturn(new LedgerBalance($senderLedger->id(), $senderLedger->currency(), 0, 50000));
    $balances->shouldReceive('apply')->once()->with(Mockery::type(LedgerEntry::class), Mockery::type(DateTimeImmutable::class));
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->times(9)->andReturn(...array_map(fn(): Uuid => Uuid::generate(), range(1, 9)));
    $publisher = new MakeTransferTestPublisher;

    $transfer = (new MakeTransfer(
        $accounts,
        $ledgers,
        $entries,
        $transfers,
        $balances,
        new MakeTransferTestClock,
        $ids,
        new MakeTransferTestTransactions,
        $publisher
    ))->handle(new MakeTransferCommand(
        $sender->id(),
        $recipient->id(),
        12500,
        'transfer-1001',
        new DateTimeImmutable('2026-09-21T11:55:00+01:00')
    ));

    expect($transfer->amount()->minorUnits())->toBe(12500)
        ->and($transfer->senderAccountId()->equals($sender->id()))->toBeTrue()
        ->and($transfer->recipientAccountId()->equals($recipient->id()))->toBeTrue()
        ->and($publisher->published)->toHaveCount(5)
        ->and($publisher->published[4])->toBeInstanceOf(TransferCompleted::class);
});

it('rejects a transfer when the locked sender balance is too low', function (): void {
    $sender = transferTestAccount('1234567890');
    $recipient = transferTestAccount('1987654321');
    $senderLedger = transferTestLedger($sender->id());
    $recipientLedger = transferTestLedger($recipient->id());
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->twice()->andReturn($sender, $recipient);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->twice()->andReturn($senderLedger, $recipientLedger);
    $transfers = Mockery::mock(TransferRepository::class);
    $transfers->shouldReceive('referenceExists')->once()->andReturnFalse();
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('findForUpdate')->once()->andReturn(new LedgerBalance($senderLedger->id(), $senderLedger->currency(), 0, 500));

    (new MakeTransfer(
        $accounts,
        $ledgers,
        Mockery::mock(LedgerEntryRepository::class),
        $transfers,
        $balances,
        new MakeTransferTestClock,
        Mockery::mock(UuidGenerator::class),
        new MakeTransferTestTransactions,
        new MakeTransferTestPublisher
    ))->handle(new MakeTransferCommand(
        $sender->id(),
        $recipient->id(),
        1000,
        'transfer-1002',
        new DateTimeImmutable
    ));
})->throws(InsufficientTransferFunds::class, 'does not have enough');

it('rejects sending money back to the same account', function (): void {
    $accountId = AccountId::generate();

    (new MakeTransfer(
        Mockery::mock(AccountRepository::class),
        Mockery::mock(LedgerRepository::class),
        Mockery::mock(LedgerEntryRepository::class),
        Mockery::mock(TransferRepository::class),
        Mockery::mock(BalanceProjectionRepository::class),
        new MakeTransferTestClock,
        Mockery::mock(UuidGenerator::class),
        new MakeTransferTestTransactions,
        new MakeTransferTestPublisher
    ))->handle(new MakeTransferCommand(
        $accountId,
        $accountId,
        1000,
        'transfer-1003',
        new DateTimeImmutable
    ));
})->throws(SameAccountTransfer::class, 'must be different');
