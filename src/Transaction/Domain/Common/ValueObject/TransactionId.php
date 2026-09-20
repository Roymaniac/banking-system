<?php

declare(strict_types=1);

namespace Transaction\Domain\Common\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Identifies one customer-facing financial transaction. */
final class TransactionId extends Uuid {}
