<?php

declare(strict_types=1);

namespace Ledger\Application\Creation;

use Account\Domain\Account\ValueObject\AccountId;
use Shared\Domain\Identifier\CorrelationId;

/** Identifies the active account that needs its financial ledger. */
final readonly class CreateLedgerCommand
{
    public function __construct(
        public AccountId $accountId,
        public ?CorrelationId $correlationId = null,
    ) {}
}
