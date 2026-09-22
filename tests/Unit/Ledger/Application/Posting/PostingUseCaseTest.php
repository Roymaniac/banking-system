<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Application\Posting\AddPosting;
use Ledger\Application\Posting\AddPostingCommand;
use Ledger\Application\Posting\PostLedgerEntry;
use Ledger\Application\Posting\PostLedgerEntryCommand;
use Ledger\Domain\Entry\Event\LedgerEntryPosted;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class PostingUseCaseClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-19T10:00:00+01:00');
    }
}

final class PostingUseCaseTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class PostingUseCasePublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function postingUseCaseLedger(): Ledger
{
    return Ledger::reconstitute(
        LedgerId::generate(),
        AccountId::generate(),
        new LedgerCurrency('NGN'),
        new DateTimeImmutable('2026-09-19T08:00:00+01:00'),
        1,
    );
}

function postingUseCaseEntry(LedgerId $originatingLedgerId): LedgerEntry
{
    return LedgerEntry::reconstitute(
        LedgerEntryId::generate(),
        $originatingLedgerId,
        new EntryReference('TRANSFER-200'),
        new EntryDescription('Customer transfer'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        new DateTimeImmutable('2026-09-19T09:01:00+01:00'),
        EntryStatus::Draft,
        1,
    );
}

it('adds balanced postings and posts the entry through application services', function (): void {
    $debitLedger = postingUseCaseLedger();
    $creditLedger = postingUseCaseLedger();
    $entry = postingUseCaseEntry($debitLedger->id());
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('findById')->times(3)->with($entry->id())->andReturn($entry);
    $entries->shouldReceive('save')->times(3)->with($entry);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findById')->once()->with($debitLedger->id())->andReturn($debitLedger);
    $ledgers->shouldReceive('findById')->once()->with($creditLedger->id())->andReturn($creditLedger);
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->times(5)->andReturn(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
    );
    $clock = new PostingUseCaseClock;
    $transactions = new PostingUseCaseTransactions;
    $publisher = new PostingUseCasePublisher;
    $addPosting = new AddPosting($entries, $ledgers, $clock, $ids, $transactions, $publisher);

    $addPosting->handle(new AddPostingCommand($entry->id(), $debitLedger->id(), PostingSide::Debit, 7500));
    $addPosting->handle(new AddPostingCommand($entry->id(), $creditLedger->id(), PostingSide::Credit, 7500));
    (new PostLedgerEntry($entries, $clock, $ids, $transactions, $publisher))
        ->handle(new PostLedgerEntryCommand($entry->id()));

    expect($entry->postings())->toHaveCount(2)
        ->and($entry->status())->toBe(EntryStatus::Posted)
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(LedgerEntryPosted::class);
});
