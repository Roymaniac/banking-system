<?php

declare(strict_types=1);

namespace Notification\Application\Outbox;

enum EmailOutboxRetryResult
{
    case Requeued;
    case NotFound;
    case StillPending;
    case Delivered;
}
