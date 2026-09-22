<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\ValueObject;

/** Draft entries cannot affect balances; only balanced posted entries can. */
enum EntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}
