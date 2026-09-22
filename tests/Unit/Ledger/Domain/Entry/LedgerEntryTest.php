<?php

declare(strict_types=1);

use Ledger\Domain\Entry\Event\LedgerEntryDrafted;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Identifier\Uuid;

it('begins as a draft that cannot yet affect balances', function (): void {
    $ledgerId = LedgerId::generate();
    $entry = LedgerEntry::draft(
        LedgerEntryId::generate(),
        $ledgerId,
        new EntryReference('deposit:2026/0001'),
        new EntryDescription('  Initial   cash deposit '),
        new DateTimeImmutable('2026-09-19T08:55:00+01:00'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        Uuid::generate(),
    );

    $event = $entry->recordedEvents()[0];

    expect($entry->reference()->value())->toBe('DEPOSIT:2026/0001')
        ->and($entry->description()->value())->toBe('Initial cash deposit')
        ->and($entry->status())->toBe(EntryStatus::Draft)
        ->and($entry->version())->toBe(1)
        ->and($event)->toBeInstanceOf(LedgerEntryDrafted::class)
        ->and($event->payload())->toBe([
            'ledger_id' => $ledgerId->value(),
            'reference' => 'DEPOSIT:2026/0001',
        ])
        ->and($event->payload())->not->toHaveKey('description');
});

it('does not allow an entry to occur after it is recorded', function (): void {
    LedgerEntry::draft(
        LedgerEntryId::generate(),
        LedgerId::generate(),
        new EntryReference('TRANSFER-1'),
        new EntryDescription('Transfer'),
        new DateTimeImmutable('2026-09-19T10:00:00+01:00'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        Uuid::generate(),
    );
})->throws(InvalidArgumentException::class, 'A ledger entry cannot occur after it is recorded.');

it('rejects an unsafe entry reference', function (): void {
    new EntryReference('reference with spaces');
})->throws(InvalidArgumentException::class, 'An entry reference must be 1 to 100');

it('rebuilds a stored entry without recording a new event', function (): void {
    $entry = LedgerEntry::reconstitute(
        LedgerEntryId::generate(),
        LedgerId::generate(),
        new EntryReference('TRANSFER-2'),
        new EntryDescription('Transfer'),
        new DateTimeImmutable('2026-09-19T08:55:00+01:00'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        EntryStatus::Draft,
        2,
    );

    expect($entry->version())->toBe(2)
        ->and($entry->recordedEvents())->toBeEmpty();
});
