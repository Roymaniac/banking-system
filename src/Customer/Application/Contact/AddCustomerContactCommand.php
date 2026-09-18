<?php

declare(strict_types=1);

namespace Customer\Application\Contact;

use Customer\Domain\Customer\Contact\ValueObject\ContactType;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries a new email address or phone number into the use case. */
final readonly class AddCustomerContactCommand
{
    public function __construct(
        public CustomerId $customerId,
        public ContactType $type,
        public string $value,
        public ?CorrelationId $correlationId = null,
    ) {}
}
