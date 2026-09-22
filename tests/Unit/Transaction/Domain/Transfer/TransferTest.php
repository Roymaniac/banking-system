<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Transfer\Event\TransferCompleted;
use Transaction\Domain\Transfer\Transfer;

it('records a transfer event without exposing the exact amount in its payload', function (): void {
    $transfer = Transfer::complete(
        new TransactionId(Uuid::generate()->value()),
        AccountId::generate(),
        AccountId::generate(),
        LedgerEntryId::generate(),
        new TransactionReference('transfer-2001'),
        new TransactionAmount(5000, new LedgerCurrency('NGN')),
        new DateTimeImmutable('2026-09-21T12:00:00+01:00'),
        Uuid::generate()
    );
    $event = $transfer->recordedEvents()[0];

    expect($event)->toBeInstanceOf(TransferCompleted::class)
        ->and($event->amount()->minorUnits())->toBe(5000)
        ->and($event->payload())->not->toHaveKey('minor_units');
});
