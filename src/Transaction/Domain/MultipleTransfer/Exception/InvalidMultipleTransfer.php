<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer\Exception;

use Shared\Domain\Exception\DomainException;

final class InvalidMultipleTransfer extends DomainException
{
    public static function empty(): self
    {
        return new self('A multiple transfer requires at least one recipient.');
    }

    public static function duplicateRecipient(): self
    {
        return new self('Each recipient may appear only once in a multiple transfer.');
    }

    public static function senderIsRecipient(): self
    {
        return new self('The sender cannot also be a recipient.');
    }
}
