<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Email\Exception;

use RuntimeException;
use Throwable;

/** Reports transport failure without exposing credentials or message contents. */
final class EmailDeliveryFailed extends RuntimeException
{
    public static function because(Throwable $previous): self
    {
        return new self('The email could not be delivered by the configured mail transport.', 0, $previous);
    }
}
