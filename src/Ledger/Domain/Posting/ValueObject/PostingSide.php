<?php

declare(strict_types=1);

namespace Ledger\Domain\Posting\ValueObject;

/** Identifies which side of double-entry bookkeeping a line belongs to. */
enum PostingSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
