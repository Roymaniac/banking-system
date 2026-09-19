<?php

declare(strict_types=1);

namespace Account\Application\Opening;

use Account\Domain\Account\ValueObject\AccountType;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries the requested account product into the creation use case. */
final readonly class CreateAccountCommand
{
    public function __construct(
        public CustomerId $customerId,
        public AccountType $type,
        public string $currency,
        public ?CorrelationId $correlationId = null,
    ) {}
}
