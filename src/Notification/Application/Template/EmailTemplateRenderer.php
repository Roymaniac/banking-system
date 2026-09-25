<?php

declare(strict_types=1);

namespace Notification\Application\Template;

use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Email\ValueObject\RecipientEmail;

/** Turns approved template data into a complete transport-independent email. */
interface EmailTemplateRenderer
{
    /** @param array<string, mixed> $variables */
    public function render(EmailTemplate $template, RecipientEmail $recipient, array $variables): EmailMessage;
}
