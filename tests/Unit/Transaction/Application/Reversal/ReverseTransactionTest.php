<?php

declare(strict_types=1);

use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Application\Reversal\ReverseTransaction;
use Transaction\Application\Reversal\ReverseTransactionCommand;
use Transaction\Domain\Reversal\Event\TransactionReversed;
use Transaction\Domain\Reversal\Exception\InsufficientReversalFunds;
use Transaction\Domain\Reversal\Exception\TransactionAlreadyReversed;
use Transaction\Domain\Reversal\Repository\ReversalRepository;
use Transaction\Domain\Reversal\Reversal;

final readonly class ReversalTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-22T14:00:00+01:00');
    }
}

final class ReversalTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class ReversalTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function reversibleTestEntry(): LedgerEntry
{
    $debitLedgerId = LedgerId::generate();
    $creditLedgerId = LedgerId::generate();
    $time = new DateTimeImmutable('2026-09-22T13:00:00+01:00');
    $entry = LedgerEntry::draft(
        LedgerEntryId::generate(),
        $debitLedgerId,
        new EntryReference('original-1001'),
        new EntryDescription('Original transfer'),
        $time,
        $time,
        Uuid::generate()
    );
    $amount = new PostingAmount(10000, new LedgerCurrency('NGN'));
    $entry->addPosting(
        PostingId::generate(),
        $debitLedgerId,
        PostingSide::Debit,
        $amount,
        $time,
        Uuid::generate()
    );
    $entry->addPosting(
        PostingId::generate(),
        $creditLedgerId,
        PostingSide::Credit,
        $amount,
        $time,
        Uuid::generate()
    );
    $entry->post($time, Uuid::generate());

    return $entry;
}

it('creates an opposite entry while preserving the original entry', function (): void {
    $original = reversibleTestEntry();
    $creditPosting = $original->postings()[1];
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('findById')->once()->with($original->id())->andReturn($original);
    $entries->shouldReceive('save')->once()->with(Mockery::on(function (LedgerEntry $entry): bool {
        return $entry->postings()[0]->side() === PostingSide::Credit
            && $entry->postings()[1]->side() === PostingSide::Debit
            && $entry->postings()[1]->amount()->minorUnits() === 10000;
    }));
    $reversals = Mockery::mock(ReversalRepository::class);
    $reversals->shouldReceive('originalEntryWasReversed')->once()->andReturnFalse();
    $reversals->shouldReceive('referenceExists')->once()->andReturnFalse();
    $reversals->shouldReceive('save')->once()->with(Mockery::type(Reversal::class));
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('findForUpdate')->once()
        ->with($creditPosting->ledgerId())
        ->andReturn(new LedgerBalance(
            $creditPosting->ledgerId(),
            $creditPosting->amount()->currency(),
            0,
            10000
        ));
    $balances->shouldReceive('apply')->once();
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->times(9)->andReturn(...array_map(fn(): Uuid => Uuid::generate(), range(1, 9)));
    $publisher = new ReversalTestPublisher;

    $reversal = (new ReverseTransaction(
        $entries,
        $reversals,
        $balances,
        new ReversalTestClock,
        $ids,
        new ReversalTestTransactions,
        $publisher
    ))->handle(new ReverseTransactionCommand(
        $original->id(),
        'reversal-1001',
        'Transfer was sent in error',
        new DateTimeImmutable('2026-09-22T13:55:00+01:00')
    ));

    expect($reversal->originalLedgerEntryId()->equals($original->id()))->toBeTrue()
        ->and($publisher->published)->toHaveCount(5)
        ->and($publisher->published[4])->toBeInstanceOf(TransactionReversed::class);
});

it('refuses to reverse the same entry twice', function (): void {
    $original = reversibleTestEntry();
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('findById')->once()->andReturn($original);
    $reversals = Mockery::mock(ReversalRepository::class);
    $reversals->shouldReceive('originalEntryWasReversed')->once()->andReturnTrue();

    (new ReverseTransaction(
        $entries,
        $reversals,
        Mockery::mock(BalanceProjectionRepository::class),
        new ReversalTestClock,
        Mockery::mock(UuidGenerator::class),
        new ReversalTestTransactions,
        new ReversalTestPublisher
    ))->handle(new ReverseTransactionCommand(
        $original->id(),
        'reversal-1002',
        'Duplicate attempt',
        new DateTimeImmutable
    ));
})->throws(TransactionAlreadyReversed::class, 'already been reversed');

it('protects a credited ledger that no longer holds the reversed amount', function (): void {
    $original = reversibleTestEntry();
    $creditPosting = $original->postings()[1];
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('findById')->once()->andReturn($original);
    $reversals = Mockery::mock(ReversalRepository::class);
    $reversals->shouldReceive('originalEntryWasReversed')->once()->andReturnFalse();
    $reversals->shouldReceive('referenceExists')->once()->andReturnFalse();
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('findForUpdate')->once()->andReturn(
        new LedgerBalance($creditPosting->ledgerId(), $creditPosting->amount()->currency(), 0, 500)
    );

    (new ReverseTransaction(
        $entries,
        $reversals,
        $balances,
        new ReversalTestClock,
        Mockery::mock(UuidGenerator::class),
        new ReversalTestTransactions,
        new ReversalTestPublisher
    ))->handle(new ReverseTransactionCommand(
        $original->id(),
        'reversal-1003',
        'Insufficient recipient funds',
        new DateTimeImmutable
    ));
})->throws(InsufficientReversalFunds::class, 'no longer has enough');
