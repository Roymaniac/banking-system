<?php

declare(strict_types=1);

namespace Notification\Application\Email;

use Notification\Domain\Email\EmailMessage;

/** Performs the immediate network delivery when a queue worker handles an email. */
interface EmailTransport
{
    public function deliver(EmailMessage $email): void;
}
