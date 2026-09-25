<?php

declare(strict_types=1);

namespace Notification\Application\Email;

use Notification\Domain\Email\EmailMessage;

/** Delivers an email without exposing framework-specific mail APIs to application code. */
interface EmailSender
{
    public function send(EmailMessage $email): void;
}
