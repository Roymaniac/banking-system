<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Application\Entry\BeginLedgerEntry;
use Ledger\Application\Entry\BeginLedgerEntryCommand;
use Ledger\Domain\Entry\Event\LedgerEntryDrafted;
use Ledger\Domain\Entry\Exception\DuplicateEntryReference;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
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

final readonly class BeginEntryTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-19T09:00:00+01:00');
    }
}

final class BeginEntryTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class BeginEntryTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function beginEntryTestLedger(): Ledger
{
    return Ledger::reconstitute(
        LedgerId::generate(),
        AccountId::generate(),
        new LedgerCurrency('NGN'),
        new DateTimeImmutable('2026-09-19T08:00:00+01:00'),
        1,
    );
}

it('stores and publishes a new draft entry', function (): void {
    $ledger = beginEntryTestLedger();
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findById')->once()->with($ledger->id())->andReturn($ledger);
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('referenceExists')->once()->with(
        $ledger->id(),
        Mockery::on(fn (EntryReference $reference): bool => $reference->value() === 'DEPOSIT-1'),
    )->andReturnFalse();
    $entries->shouldReceive('save')->once()->with(Mockery::type(LedgerEntry::class));
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->twice()->andReturn(Uuid::generate(), Uuid::generate());
    $publisher = new BeginEntryTestPublisher;

    $entry = (new BeginLedgerEntry(
        $ledgers,
        $entries,
        new BeginEntryTestClock,
        $ids,
        new BeginEntryTestTransactions,
        $publisher,
    ))->handle(new BeginLedgerEntryCommand(
        $ledger->id(),
        'deposit-1',
        'Cash deposit',
        new DateTimeImmutable('2026-09-19T08:55:00+01:00'),
    ));

    expect($entry->status())->toBe(EntryStatus::Draft)
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(LedgerEntryDrafted::class);
});

it('rejects a duplicate reference within the same ledger', function (): void {
    $ledger = beginEntryTestLedger();
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findById')->once()->andReturn($ledger);
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('referenceExists')->once()->andReturnTrue();

    (new BeginLedgerEntry(
        $ledgers,
        $entries,
        new BeginEntryTestClock,
        Mockery::mock(UuidGenerator::class),
        new BeginEntryTestTransactions,
        new BeginEntryTestPublisher,
    ))->handle(new BeginLedgerEntryCommand(
        $ledger->id(),
        'deposit-1',
        'Cash deposit',
        new DateTimeImmutable('2026-09-19T08:55:00+01:00'),
    ));
})->throws(DuplicateEntryReference::class, 'same reference');
