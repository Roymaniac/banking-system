<?php

declare(strict_types=1);

namespace Transaction\Application\Transfer\View;

use Account\Domain\Account\ValueObject\AccountId;

/** Carries only the recipient details needed on a transfer confirmation screen. */
final readonly class RecipientAccountView
{
    public function __construct(
        public AccountId $accountId,
        public string $displayName,
        public string $accountLastFour,
        public string $currency,
    ) {}
}
