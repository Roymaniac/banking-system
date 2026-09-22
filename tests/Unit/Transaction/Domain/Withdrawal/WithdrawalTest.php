<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Withdrawal\Event\WithdrawalCompleted;
use Transaction\Domain\Withdrawal\Withdrawal;

it('records a privacy-safe completion event', function (): void {
    $withdrawal = Withdrawal::complete(new TransactionId(Uuid::generate()->value()), AccountId::generate(), LedgerEntryId::generate(), new TransactionReference('atm-2001'), new TransactionAmount(15000, new LedgerCurrency('NGN')), new DateTimeImmutable('2026-09-21T10:00:00+01:00'), Uuid::generate());
    $event = $withdrawal->recordedEvents()[0];

    expect($event)->toBeInstanceOf(WithdrawalCompleted::class)
        ->and($event->amount()->minorUnits())->toBe(15000)
        ->and($event->payload())->not->toHaveKey('minor_units');
});
