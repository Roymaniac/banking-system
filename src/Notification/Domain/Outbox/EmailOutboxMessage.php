<?php

declare(strict_types=1);

namespace Notification\Domain\Outbox;

use DateTimeImmutable;
use Notification\Domain\Email\EmailMessage;
use Shared\Domain\Identifier\Uuid;

/** A durable email request waiting for a background delivery attempt. */
final readonly class EmailOutboxMessage
{
    public function __construct(
        private Uuid $id,
        private EmailMessage $email,
        private DateTimeImmutable $recordedAt,
        private int $attempts = 0,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function email(): EmailMessage
    {
        return $this->email;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }
}
