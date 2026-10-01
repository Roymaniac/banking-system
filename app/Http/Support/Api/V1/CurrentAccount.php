<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use Account\Domain\Account\Account;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Illuminate\Http\Request;

/** Finds an account only when it belongs to the authenticated customer. */
final readonly class CurrentAccount
{
    public function __construct(
        private CurrentCustomer $currentCustomer,
        private AccountRepository $accounts,
    ) {}

    public function find(Request $request, AccountId $accountId): ?Account
    {
        $customer = $this->currentCustomer->profile($request);
        $account = $this->accounts->findById($accountId);

        if ($customer === null || $account === null) {
            return null;
        }

        return $account->customerId()->equals($customer->id()) ? $account : null;
    }
}
