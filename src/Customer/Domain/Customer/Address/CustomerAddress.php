<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Address;

use Customer\Domain\Customer\Address\ValueObject\AddressId;
use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\Address\ValueObject\PostalAddress;
use Shared\Domain\Entity\Entity;

/** An address is an identifiable record owned by one Customer aggregate. */
final class CustomerAddress extends Entity
{
    public function __construct(
        private readonly AddressId $id,
        private AddressType $type,
        private PostalAddress $details,
    ) {}

    public function id(): AddressId
    {
        return $this->id;
    }

    public function type(): AddressType
    {
        return $this->type;
    }

    public function details(): PostalAddress
    {
        return $this->details;
    }

    /** Changes the address while keeping its stable identity. */
    public function update(AddressType $type, PostalAddress $details): void
    {
        $this->type = $type;
        $this->details = $details;
    }
}
