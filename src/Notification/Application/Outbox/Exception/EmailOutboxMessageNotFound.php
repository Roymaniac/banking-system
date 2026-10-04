<?php

declare(strict_types=1);

namespace Notification\Application\Outbox\Exception;

use RuntimeException;

final class EmailOutboxMessageNotFound extends RuntimeException
{
    public static function create(): self
    {
        return new self('The requested email outbox message does not exist.');
    }
}
