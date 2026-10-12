<?php

declare(strict_types=1);

namespace Transaction\Domain\Risk\Exception;

use Shared\Domain\Exception\DomainException;

final class TransferRiskCaseNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('Transfer risk case not found.');
    }
}
