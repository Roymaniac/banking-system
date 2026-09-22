<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Application\Balance\GetLedgerBalance;
use Ledger\Application\Balance\ProjectLedgerBalance;
use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
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
use Ledger\Domain\Posting\Posting;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\Uuid;

final class BalanceProjectionTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

function balanceProjectionTestEntry(): LedgerEntry
{
    $ledgerId = LedgerId::generate();

    return LedgerEntry::reconstitute(
        LedgerEntryId::generate(),
        $ledgerId,
        new EntryReference('TRANSFER-300'),
        new EntryDescription('Customer transfer'),
        new DateTimeImmutable('2026-09-20T09:00:00+01:00'),
        new DateTimeImmutable('2026-09-20T09:01:00+01:00'),
        EntryStatus::Posted,
        4,
        [
            new Posting(
                PostingId::generate(),
                $ledgerId,
                PostingSide::Debit,
                new PostingAmount(5000, new LedgerCurrency('NGN')),
            ),
            new Posting(
                PostingId::generate(),
                LedgerId::generate(),
                PostingSide::Credit,
                new PostingAmount(5000, new LedgerCurrency('NGN')),
            ),
        ],
    );
}

it('loads the posted entry and applies it to the projection', function (): void {
    $entry = balanceProjectionTestEntry();
    $event = new LedgerEntryPosted(
        Uuid::generate(),
        $entry->id(),
        $entry->version(),
        new DateTimeImmutable('2026-09-20T09:02:00+01:00'),
        2,
        5000,
        new LedgerCurrency('NGN'),
    );
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('findById')->once()->with(Mockery::on(
        fn (LedgerEntryId $id): bool => $id->equals($entry->id()),
    ))->andReturn($entry);
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('apply')->once()->with($entry, $event->occurredOn());

    (new ProjectLedgerBalance($entries, $balances, new BalanceProjectionTestTransactions))
        ->handle($event);

    expect(true)->toBeTrue();
});

it('returns zero before a ledger receives its first posted entry', function (): void {
    $ledger = Ledger::reconstitute(
        LedgerId::generate(),
        AccountId::generate(),
        new LedgerCurrency('NGN'),
        new DateTimeImmutable('2026-09-20T08:00:00+01:00'),
        1,
    );
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findById')->once()->with($ledger->id())->andReturn($ledger);
    $balances = Mockery::mock(BalanceProjectionRepository::class);
    $balances->shouldReceive('find')->once()->with($ledger->id())->andReturnNull();

    $balance = (new GetLedgerBalance($ledgers, $balances))->handle($ledger->id());

    expect($balance)->toBeInstanceOf(LedgerBalance::class)
        ->and($balance->balanceMinorUnits())->toBe(0)
        ->and($balance->currency()->value())->toBe('NGN');
});
