<?php

declare(strict_types=1);

namespace Notification\Domain\Outbox\Repository;

use DateTimeImmutable;
use Notification\Domain\Outbox\EmailOutboxMessage;
use Shared\Domain\Identifier\Uuid;

/** Stores encrypted email requests until a worker confirms delivery. */
interface EmailOutboxRepository
{
    public function add(EmailOutboxMessage $message): void;

    /** @return list<EmailOutboxMessage> */
    public function pending(int $limit): array;

    public function markDelivered(Uuid $id, DateTimeImmutable $deliveredAt): void;

    public function recordFailure(Uuid $id, DateTimeImmutable $failedAt): void;
}
