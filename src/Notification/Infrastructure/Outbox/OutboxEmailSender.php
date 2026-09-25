<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Outbox;

use Notification\Application\Email\EmailSender;
use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Outbox\EmailOutboxMessage;
use Notification\Domain\Outbox\Repository\EmailOutboxRepository;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\UuidGenerator;

/** Records email intent durably instead of relying on an in-memory dispatch. */
final readonly class OutboxEmailSender implements EmailSender
{
    public function __construct(
        private EmailOutboxRepository $outbox,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
    ) {}

    public function send(EmailMessage $email): void
    {
        $this->outbox->add(
            new EmailOutboxMessage(
                $this->uuidGenerator->generate(),
                $email,
                $this->clock->now()
            )
        );
    }
}
