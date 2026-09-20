<?php

declare(strict_types=1);

namespace Ledger\Infrastructure\Balance;

use Account\Application\Closure\AccountClosureBalanceChecker;
use Account\Domain\Account\Exception\NonZeroAccountBalance;
use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Ledger\Repository\LedgerRepository;

/** Uses the authoritative ledger projection to protect account closure. */
final readonly class ProjectedAccountClosureBalanceChecker implements AccountClosureBalanceChecker
{
    public function __construct(
        private LedgerRepository $ledgers,
        private BalanceProjectionRepository $balances,
    ) {}

    public function assertZeroBalance(AccountId $accountId): void
    {
        $ledger = $this->ledgers->findByAccountId($accountId);

        // No ledger or no postings means the account has never held funds.
        if ($ledger === null) {
            return;
        }

        $balance = $this->balances->find($ledger->id());

        if ($balance !== null && ! $balance->isZero()) {
            throw NonZeroAccountBalance::create();
        }
    }
}
