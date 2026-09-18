<?php

declare(strict_types=1);

namespace Customer\Application\Contact;

use Customer\Domain\Customer\Contact\ValueObject\ContactId;
use Customer\Domain\Customer\Contact\ValueObject\ContactType;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries replacement details for an existing contact record. */
final readonly class UpdateCustomerContactCommand
{
    public function __construct(
        public CustomerId $customerId,
        public ContactId $contactId,
        public ContactType $type,
        public string $value,
        public ?CorrelationId $correlationId = null,
    ) {}
}
