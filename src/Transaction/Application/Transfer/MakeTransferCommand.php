<?php

declare(strict_types=1);

namespace Transaction\Application\Transfer;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Shared\Domain\Identifier\CorrelationId;

/** Carries one request to move money between two customer accounts. */
final readonly class MakeTransferCommand
{
    public function __construct(
        public AccountId $senderAccountId,
        public AccountId $recipientAccountId,
        public int $minorUnits,
        public string $reference,
        public DateTimeImmutable $occurredAt,
        public ?CorrelationId $correlationId = null,
    ) {}
}
