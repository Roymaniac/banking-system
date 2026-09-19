<?php

declare(strict_types=1);

namespace Account\Domain\Account\ValueObject;

/**
 * Describes whether an account can currently be used.
 */
enum AccountStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';
}
