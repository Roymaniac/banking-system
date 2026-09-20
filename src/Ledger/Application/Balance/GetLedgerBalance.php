<?php

declare(strict_types=1);

namespace Ledger\Application\Balance;

use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\Exception\LedgerNotFound;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerId;

/** Returns the current projected balance, including zero for a new ledger. */
final readonly class GetLedgerBalance
{
    public function __construct(
        private LedgerRepository $ledgers,
        private BalanceProjectionRepository $balances,
    ) {}

    public function handle(LedgerId $ledgerId): LedgerBalance
    {
        $ledger = $this->ledgers->findById($ledgerId);

        if ($ledger === null) {
            throw LedgerNotFound::create();
        }

        return $this->balances->find($ledgerId)
            ?? LedgerBalance::zero($ledgerId, $ledger->currency());
    }
}
