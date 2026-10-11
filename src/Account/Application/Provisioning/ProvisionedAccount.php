<?php

declare(strict_types=1);

namespace Account\Application\Provisioning;

use Account\Domain\Account\Account;
use Ledger\Domain\Ledger\Ledger;

/** Returns both records created or changed by successful account provisioning. */
final readonly class ProvisionedAccount
{
    public function __construct(
        public Account $account,
        public Ledger $ledger,
    ) {}
}
