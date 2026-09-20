<?php

declare(strict_types=1);

namespace Ledger\Domain\Posting;

use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Domain\Entity\Entity;

/** One immutable debit or credit line directed at a specific ledger. */
final class Posting extends Entity
{
    public function __construct(
        private readonly PostingId $id,
        private readonly LedgerId $ledgerId,
        private readonly PostingSide $side,
        private readonly PostingAmount $amount,
    ) {}

    public function id(): PostingId
    {
        return $this->id;
    }

    public function ledgerId(): LedgerId
    {
        return $this->ledgerId;
    }

    public function side(): PostingSide
    {
        return $this->side;
    }

    public function amount(): PostingAmount
    {
        return $this->amount;
    }
}
