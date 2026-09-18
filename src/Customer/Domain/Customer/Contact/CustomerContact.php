<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Contact;

use Customer\Domain\Customer\Contact\ValueObject\ContactId;
use Customer\Domain\Customer\Contact\ValueObject\ContactPoint;
use Shared\Domain\Entity\Entity;

/** A contact is an identifiable communication channel owned by a customer. */
final class CustomerContact extends Entity
{
    public function __construct(
        private readonly ContactId $id,
        private ContactPoint $contactPoint,
    ) {}

    public function id(): ContactId
    {
        return $this->id;
    }

    public function contactPoint(): ContactPoint
    {
        return $this->contactPoint;
    }

    public function update(ContactPoint $contactPoint): void
    {
        $this->contactPoint = $contactPoint;
    }
}
