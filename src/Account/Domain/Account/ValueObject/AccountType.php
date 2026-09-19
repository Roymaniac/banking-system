<?php

declare(strict_types=1);

namespace Account\Domain\Account\ValueObject;

/** Lists the account products currently available to customers. */
enum AccountType: string
{
    case Savings = 'savings';
    case Current = 'current';
}
