<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Permanently identifies one financial event recorded in a ledger. */
final class LedgerEntryId extends Uuid {}
