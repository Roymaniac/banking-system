<?php

declare(strict_types=1);

namespace Transaction\Application\MultipleTransfer;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Shared\Domain\Identifier\CorrelationId;

/** Carries one sender and every recipient that must be paid together. */
final readonly class MakeMultipleTransferCommand
{
    /** @param list<TransferRecipient> $recipients */
    public function __construct(
        public AccountId $senderAccountId,
        public array $recipients,
        public string $reference,
        public DateTimeImmutable $occurredAt,
        public ?CorrelationId $correlationId = null,
    ) {}
}
