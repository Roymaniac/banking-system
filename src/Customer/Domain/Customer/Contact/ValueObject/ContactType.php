<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Contact\ValueObject;

/** Lists the contact channels currently supported by the bank. */
enum ContactType: string
{
    case Email = 'email';
    case Phone = 'phone';
}
