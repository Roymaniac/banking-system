<?php

declare(strict_types=1);

namespace Transaction\Domain\Risk\Exception;

use Shared\Domain\Exception\DomainException;

/** Stops unusually frequent outgoing transfers until the rolling window clears. */
final class SuspiciousTransferVelocity extends DomainException
{
    private function __construct(
        public readonly int $windowMinutes,
        public readonly int $maximumTransfers,
    ) {
        parent::__construct('This transfer was blocked because the account has made too many recent transfers.');
    }

    public static function create(int $windowMinutes, int $maximumTransfers): self
    {
        return new self($windowMinutes, $maximumTransfers);
    }
}
