<?php

declare(strict_types=1);

namespace Transaction\Application\Deposit;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries a deposit request from its funding ledger to a customer account. */
final readonly class MakeDepositCommand
{
    public function __construct(
        public AccountId $accountId,
        public LedgerId $fundingLedgerId,
        public int $minorUnits,
        public string $reference,
        public DateTimeImmutable $occurredAt,
        public ?CorrelationId $correlationId = null,
    ) {}
}
