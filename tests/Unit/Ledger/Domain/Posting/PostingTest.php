<?php

declare(strict_types=1);

use Ledger\Domain\Entry\Event\LedgerEntryPosted;
use Ledger\Domain\Entry\Event\PostingAdded;
use Ledger\Domain\Entry\Exception\DuplicateLedgerPosting;
use Ledger\Domain\Entry\Exception\OriginatingLedgerPostingRequired;
use Ledger\Domain\Entry\Exception\PostedEntryCannotBeChanged;
use Ledger\Domain\Entry\Exception\PostingCurrencyMismatch;
use Ledger\Domain\Entry\Exception\UnbalancedLedgerEntry;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Domain\Identifier\Uuid;

function postingTestEntry(): LedgerEntry
{
    return LedgerEntry::reconstitute(
        LedgerEntryId::generate(),
        LedgerId::generate(),
        new EntryReference('TRANSFER-100'),
        new EntryDescription('Customer transfer'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        new DateTimeImmutable('2026-09-19T09:01:00+01:00'),
        EntryStatus::Draft,
        1,
    );
}

function addPostingToTestEntry(
    LedgerEntry $entry,
    LedgerId $ledgerId,
    PostingSide $side,
    int $minorUnits,
    string $currency = 'NGN',
): void {
    $entry->addPosting(
        PostingId::generate(),
        $ledgerId,
        $side,
        new PostingAmount($minorUnits, new LedgerCurrency($currency)),
        new DateTimeImmutable('2026-09-19T09:02:00+01:00'),
        Uuid::generate(),
    );
}

it('stores monetary amounts as positive integer minor units', function (): void {
    $amount = new PostingAmount(1050, new LedgerCurrency('NGN'));

    expect($amount->minorUnits())->toBe(1050)
        ->and($amount->currency()->value())->toBe('NGN');
});

it('rejects zero or negative posting amounts', function (): void {
    new PostingAmount(0, new LedgerCurrency('NGN'));
})->throws(InvalidArgumentException::class, 'A posting amount must be greater than zero.');

it('adds a posting without exposing its amount in the generic event payload', function (): void {
    $entry = postingTestEntry();
    addPostingToTestEntry($entry, $entry->ledgerId(), PostingSide::Debit, 5000);
    $event = $entry->recordedEvents()[0];

    expect($entry->postings())->toHaveCount(1)
        ->and($event)->toBeInstanceOf(PostingAdded::class)
        ->and($event->payload())->not->toHaveKey('minor_units');
});

it('posts an entry only when debits and credits balance', function (): void {
    $entry = postingTestEntry();
    addPostingToTestEntry($entry, $entry->ledgerId(), PostingSide::Debit, 5000);
    addPostingToTestEntry($entry, LedgerId::generate(), PostingSide::Credit, 5000);
    $entry->pullDomainEvents();

    $entry->post(new DateTimeImmutable('2026-09-19T09:03:00+01:00'), Uuid::generate());
    $event = $entry->recordedEvents()[0];

    expect($entry->status())->toBe(EntryStatus::Posted)
        ->and($entry->version())->toBe(4)
        ->and($event)->toBeInstanceOf(LedgerEntryPosted::class)
        ->and($event->totalMinorUnits())->toBe(5000)
        ->and($event->payload())->toBe(['posting_count' => 2, 'currency' => 'NGN']);
});

it('rejects an unbalanced entry', function (): void {
    $entry = postingTestEntry();
    addPostingToTestEntry($entry, $entry->ledgerId(), PostingSide::Debit, 5000);
    addPostingToTestEntry($entry, LedgerId::generate(), PostingSide::Credit, 4000);
    $entry->post(new DateTimeImmutable, Uuid::generate());
})->throws(UnbalancedLedgerEntry::class, 'Total debits must equal total credits');

it('requires the originating ledger to participate in the entry', function (): void {
    $entry = postingTestEntry();
    addPostingToTestEntry($entry, LedgerId::generate(), PostingSide::Debit, 5000);
    addPostingToTestEntry($entry, LedgerId::generate(), PostingSide::Credit, 5000);
    $entry->post(new DateTimeImmutable, Uuid::generate());
})->throws(OriginatingLedgerPostingRequired::class, 'The originating ledger must be included');

it('rejects two postings directed at the same ledger', function (): void {
    $entry = postingTestEntry();
    $ledgerId = $entry->ledgerId();
    addPostingToTestEntry($entry, $ledgerId, PostingSide::Debit, 5000);
    addPostingToTestEntry($entry, $ledgerId, PostingSide::Credit, 5000);
})->throws(DuplicateLedgerPosting::class, 'only one posting for each ledger');

it('rejects mixed currencies within one entry', function (): void {
    $entry = postingTestEntry();
    addPostingToTestEntry($entry, $entry->ledgerId(), PostingSide::Debit, 5000, 'NGN');
    addPostingToTestEntry($entry, LedgerId::generate(), PostingSide::Credit, 5000, 'USD');
})->throws(PostingCurrencyMismatch::class, 'must use the same currency');

it('never allows a posted entry to change', function (): void {
    $entry = postingTestEntry();
    addPostingToTestEntry($entry, $entry->ledgerId(), PostingSide::Debit, 5000);
    addPostingToTestEntry($entry, LedgerId::generate(), PostingSide::Credit, 5000);
    $entry->post(new DateTimeImmutable, Uuid::generate());
    addPostingToTestEntry($entry, LedgerId::generate(), PostingSide::Debit, 5000);
})->throws(PostedEntryCannotBeChanged::class, 'permanent and cannot be changed');
