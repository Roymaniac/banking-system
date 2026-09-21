<?php

declare(strict_types=1);

namespace Transaction\Application\Withdrawal;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries a request to move money from a customer account to a disbursement ledger. */
final readonly class MakeWithdrawalCommand
{
    public function __construct(
        public AccountId $accountId,
        public LedgerId $disbursementLedgerId,
        public int $minorUnits,
        public string $reference,
        public DateTimeImmutable $occurredAt,
        public ?CorrelationId $correlationId = null,
    ) {}
}
