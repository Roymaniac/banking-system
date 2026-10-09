<?php

declare(strict_types=1);

namespace Transaction\Application\Control\Exception;

use Shared\Domain\Exception\DomainException;

final class InvalidMoneyMovementResume extends DomainException
{
    public static function whileEnabled(): self
    {
        return new self('Money movement is already enabled.');
    }

    public static function pendingRequestExists(): self
    {
        return new self('A non-expired resume request is already awaiting approval.');
    }

    public static function requestNotFound(): self
    {
        return new self('The resume request is not available for approval.');
    }

    public static function sameOperatorCannotReject(): self
    {
        return new self('The requester must cancel their own request; a different operator may reject it.');
    }

    public static function onlyRequesterCanCancel(): self
    {
        return new self('Only the operator who created the resume request may cancel it.');
    }

    public static function sameOperator(): self
    {
        return new self('A different operator must approve the resume request.');
    }

    public static function expired(): self
    {
        return new self('The resume request has expired. Submit a new request.');
    }

    public static function superseded(): self
    {
        return new self('A newer suspension replaced this resume request. Submit a new request after investigating it.');
    }

    public static function breakGlassNotAuthorized(): self
    {
        return new self('The named operator does not have the money_movement.break_glass permission.');
    }
}
