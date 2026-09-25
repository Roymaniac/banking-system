<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Queue;

use Illuminate\Contracts\Bus\Dispatcher;
use Notification\Application\Email\EmailSender;
use Notification\Domain\Email\EmailMessage;

/** Places email work on the queue so user requests do not wait for SMTP. */
final readonly class QueuedEmailSender implements EmailSender
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function send(EmailMessage $email): void
    {
        $this->dispatcher->dispatch(new SendEmailJob($email));
    }
}
