<?php

declare(strict_types=1);

use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Reversal\Event\TransactionReversed;
use Transaction\Domain\Reversal\Reversal;
use Transaction\Domain\Reversal\ValueObject\ReversalReason;

it('records an auditable link to both ledger entries', function (): void {
    $originalId = LedgerEntryId::generate();
    $reversalEntryId = LedgerEntryId::generate();
    $reversal = Reversal::complete(
        new TransactionId(Uuid::generate()->value()),
        $originalId,
        $reversalEntryId,
        new TransactionReference('reversal-2001'),
        new ReversalReason('Duplicate payment'),
        new DateTimeImmutable('2026-09-22T14:00:00+01:00'),
        Uuid::generate()
    );
    $event = $reversal->recordedEvents()[0];

    expect($event)->toBeInstanceOf(TransactionReversed::class)
        ->and($event->payload()['original_ledger_entry_id'])->toBe($originalId->value())
        ->and($event->payload()['reversal_ledger_entry_id'])->toBe($reversalEntryId->value());
});
