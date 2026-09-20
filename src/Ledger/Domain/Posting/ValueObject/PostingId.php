<?php

declare(strict_types=1);

namespace Ledger\Domain\Posting\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Identifies one debit or credit line within a ledger entry. */
final class PostingId extends Uuid {}
