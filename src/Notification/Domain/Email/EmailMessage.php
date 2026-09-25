<?php

declare(strict_types=1);

namespace Notification\Domain\Email;

use Notification\Domain\Email\ValueObject\EmailBody;
use Notification\Domain\Email\ValueObject\EmailSubject;
use Notification\Domain\Email\ValueObject\RecipientEmail;

/** A transport-independent description of one plain-text email. */
final readonly class EmailMessage
{
    public function __construct(
        private RecipientEmail $recipient,
        private EmailSubject $subject,
        private EmailBody $body,
    ) {}

    public function recipient(): RecipientEmail
    {
        return $this->recipient;
    }

    public function subject(): EmailSubject
    {
        return $this->subject;
    }

    public function body(): EmailBody
    {
        return $this->body;
    }
}
