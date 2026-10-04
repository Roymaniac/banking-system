<?php

declare(strict_types=1);

namespace Notification\Application\Outbox\Exception;

use RuntimeException;

final class EmailOutboxMessageCannotRetry extends RuntimeException
{
    public static function stillPending(): self
    {
        return new self('This email is still eligible for automatic delivery and does not need a manual retry.');
    }

    public static function delivered(): self
    {
        return new self('A delivered email cannot be retried.');
    }
}
