<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Template;

use Illuminate\Contracts\View\Factory;
use Notification\Application\Template\EmailTemplate;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Application\Template\Exception\InvalidTemplateData;
use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Email\ValueObject\EmailBody;
use Notification\Domain\Email\ValueObject\EmailSubject;
use Notification\Domain\Email\ValueObject\RecipientEmail;

/** Renders the approved catalogue through Laravel Blade. */
final readonly class BladeEmailTemplateRenderer implements EmailTemplateRenderer
{
    public function __construct(private Factory $views) {}

    public function render(EmailTemplate $template, RecipientEmail $recipient, array $variables): EmailMessage
    {
        $missingVariables = array_values(array_diff($template->requiredVariables(), array_keys($variables)));

        if ($missingVariables !== []) {
            throw InvalidTemplateData::missing($template, $missingVariables);
        }

        return new EmailMessage(
            $recipient,
            new EmailSubject($template->subject()),
            new EmailBody($this->views->make($template->view(), $variables)->render()),
        );
    }
}
