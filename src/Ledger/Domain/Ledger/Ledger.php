<?php

declare(strict_types=1);

namespace Ledger\Domain\Ledger;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Ledger\Event\LedgerCreated;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * A Ledger is the authoritative financial record for exactly one account.
 */
final class Ledger extends AggregateRoot
{
    private function __construct(
        private readonly LedgerId $id,
        private readonly AccountId $accountId,
        private readonly LedgerCurrency $currency,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        LedgerId $id,
        AccountId $accountId,
        LedgerCurrency $currency,
        DateTimeImmutable $createdAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): self {
        $ledger = new self($id, $accountId, $currency, $createdAt);
        $ledger->record(new LedgerCreated(
            $eventId,
            $id,
            $createdAt,
            $accountId,
            $currency,
            $correlationId,
        ));

        return $ledger;
    }

    /** Rebuilds a stored ledger without recording another creation event. */
    public static function reconstitute(
        LedgerId $id,
        AccountId $accountId,
        LedgerCurrency $currency,
        DateTimeImmutable $createdAt,
        int $version,
    ): self {
        $ledger = new self($id, $accountId, $currency, $createdAt);
        $ledger->reconstituteAtVersion($version);

        return $ledger;
    }

    public function id(): LedgerId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function currency(): LedgerCurrency
    {
        return $this->currency;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
