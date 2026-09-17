<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Address\ValueObject;

/** Explains how the bank is allowed to use an address. */
enum AddressType: string
{
    case Residential = 'residential';
    case Mailing = 'mailing';
}
