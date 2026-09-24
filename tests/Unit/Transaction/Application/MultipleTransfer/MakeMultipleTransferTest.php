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
use Transaction\Application\MultipleTransfer\MakeMultipleTransfer;
use Transaction\Application\MultipleTransfer\MakeMultipleTransferCommand;
use Transaction\Application\MultipleTransfer\TransferRecipient;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;
use Transaction\Domain\MultipleTransfer\Event\MultipleTransferCompleted;
use Transaction\Domain\MultipleTransfer\Exception\InsufficientMultipleTransferFunds;
use Transaction\Domain\MultipleTransfer\Exception\InvalidMultipleTransfer;
use Transaction\Domain\MultipleTransfer\MultipleTransfer;
use Transaction\Domain\MultipleTransfer\Repository\MultipleTransferRepository;

final readonly class MultipleTransferTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-22T10:00:00+01:00');
    }
}

final class MultipleTransferTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class MultipleTransferTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function multipleTransferTestAccount(string $number): Account
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

function multipleTransferTestLedger(AccountId $accountId): Ledger
{
    return Ledger::reconstitute(
        LedgerId::generate(),
        $accountId,
        new LedgerCurrency('NGN'),
        new DateTimeImmutable('2026-09-19T10:00:00+01:00'),
        1
    );
}

it('pays every recipient through one atomic balanced entry', function (): void {
    $sender = multipleTransferTestAccount('1234567890');
    $firstRecipient = multipleTransferTestAccount('2234567890');
    $secondRecipient = multipleTransferTestAccount('3234567890');
    $senderLedger = multipleTransferTestLedger($sender->id());
    $firstLedger = multipleTransferTestLedger($firstRecipient->id());
    $secondLedger = multipleTransferTestLedger($secondRecipient->id());
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->times(3)->andReturn($sender, $firstRecipient, $secondRecipient);
    $accounts->shouldReceive('findByIdForUpdate')->times(3)->andReturnUsing(
        fn(AccountId $id): Account => match ($id->value()) {
            $sender->id()->value() => $sender,
            $firstRecipient->id()->value() => $firstRecipient,
            default => $secondRecipient,
        },
    );
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->times(3)->andReturn($senderLedger, $firstLedger, $secondLedger);
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('save')->once()->with(Mockery::on(function (LedgerEntry $entry): bool {
        $amounts = array_map(fn($posting): int => $posting->amount()->minorUnits(), $entry->postings());

        return $amounts === [30000, 10000, 20000];
    }));
    $repository = Mockery::mock(MultipleTransferRepository::class);
    $repository->shouldReceive('referenceExists')->once()->andReturnFalse();
    $repository->shouldReceive('save')->once()->with(Mockery::type(MultipleTransfer::class));
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('findForUpdate')->once()->andReturn(
        new LedgerBalance(
            $senderLedger->id(),
            $senderLedger->currency(),
            0,
            50000
        )
    );
    $balances->shouldReceive('apply')->once();
    $limits = Mockery::mock(DailyTransactionLimitRepository::class);
    $limits->shouldReceive('consume')->once()->with(
        $sender->id(),
        $senderLedger->currency(),
        30000,
        Mockery::type(DateTimeImmutable::class)
    );
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->times(11)->andReturn(...array_map(
        fn(): Uuid => Uuid::generate(),
        range(1, 11)
    ));
    $publisher = new MultipleTransferTestPublisher;

    $transfer = (new MakeMultipleTransfer(
        $accounts,
        $ledgers,
        $entries,
        $repository,
        $balances,
        $limits,
        new MultipleTransferTestClock,
        $ids,
        new MultipleTransferTestTransactions,
        $publisher
    ))->handle(new MakeMultipleTransferCommand(
        $sender->id(),
        [
            new TransferRecipient($firstRecipient->id(), 10000),
            new TransferRecipient($secondRecipient->id(), 20000),
        ],
        'batch-1001',
        new DateTimeImmutable('2026-09-22T09:55:00+01:00')
    ));

    expect($transfer->totalAmount()->minorUnits())->toBe(30000)
        ->and($transfer->items())->toHaveCount(2)
        ->and($publisher->published)->toHaveCount(6)
        ->and($publisher->published[5])->toBeInstanceOf(MultipleTransferCompleted::class)
        ->and($publisher->published[5]->payload())->not->toHaveKey('minor_units');
});

it('rejects the whole batch when the sender cannot cover its total', function (): void {
    $sender = multipleTransferTestAccount('1234567890');
    $recipient = multipleTransferTestAccount('2234567890');
    $senderLedger = multipleTransferTestLedger($sender->id());
    $recipientLedger = multipleTransferTestLedger($recipient->id());
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->twice()->andReturn($sender, $recipient);
    $accounts->shouldReceive('findByIdForUpdate')->twice()->andReturnUsing(
        fn(AccountId $id): Account => $id->equals($sender->id()) ? $sender : $recipient,
    );
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->twice()->andReturn($senderLedger, $recipientLedger);
    $repository = Mockery::mock(MultipleTransferRepository::class);
    $repository->shouldReceive('referenceExists')->once()->andReturnFalse();
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('findForUpdate')->once()->andReturn(
        new LedgerBalance(
            $senderLedger->id(),
            $senderLedger->currency(),
            0,
            500
        )
    );

    (new MakeMultipleTransfer(
        $accounts,
        $ledgers,
        Mockery::mock(LedgerEntryRepository::class),
        $repository,
        $balances,
        Mockery::mock(DailyTransactionLimitRepository::class),
        new MultipleTransferTestClock,
        Mockery::mock(UuidGenerator::class),
        new MultipleTransferTestTransactions,
        new MultipleTransferTestPublisher
    ))->handle(new MakeMultipleTransferCommand(
        $sender->id(),
        [new TransferRecipient($recipient->id(), 1000)],
        'batch-1002',
        new DateTimeImmutable
    ));
})->throws(InsufficientMultipleTransferFunds::class, 'all recipients');

it('rejects an empty recipient list', function (): void {
    (new MakeMultipleTransfer(
        Mockery::mock(AccountRepository::class),
        Mockery::mock(LedgerRepository::class),
        Mockery::mock(LedgerEntryRepository::class),
        Mockery::mock(MultipleTransferRepository::class),
        Mockery::mock(BalanceProjectionRepository::class),
        Mockery::mock(DailyTransactionLimitRepository::class),
        new MultipleTransferTestClock,
        Mockery::mock(UuidGenerator::class),
        new MultipleTransferTestTransactions,
        new MultipleTransferTestPublisher
    ))->handle(new MakeMultipleTransferCommand(
        AccountId::generate(),
        [],
        'batch-1003',
        new DateTimeImmutable
    ));
})->throws(InvalidMultipleTransfer::class, 'at least one recipient');
