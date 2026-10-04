<?php

declare(strict_types=1);

namespace Notification\Application\Outbox;

use DateTimeImmutable;
use Shared\Domain\Identifier\Uuid;

/** Atomically makes an exhausted outbox message eligible for delivery again. */
interface EmailOutboxRetryGateway
{
    public function retry(Uuid $id, DateTimeImmutable $requeuedAt): EmailOutboxRetryResult;
}
