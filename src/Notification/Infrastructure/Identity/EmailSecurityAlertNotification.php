<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Identity;

use DateTimeImmutable;
use Identity\Application\Security\SecurityAlert;
use Identity\Application\Security\SecurityAlertNotifier;
use Identity\Domain\User\ValueObject\EmailAddress;
use Notification\Application\Email\EmailSender;
use Notification\Application\Template\EmailTemplate;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Domain\Email\ValueObject\RecipientEmail;

/** Builds a privacy-safe email for completed account-security actions. */
final readonly class EmailSecurityAlertNotification implements SecurityAlertNotifier
{
    public function __construct(
        private EmailTemplateRenderer $templates,
        private EmailSender $emails,
    ) {}

    public function send(
        EmailAddress $email,
        SecurityAlert $alert,
        DateTimeImmutable $occurredAt,
    ): void {
        $message = $this->templates->render(
            EmailTemplate::SecurityAlert,
            new RecipientEmail($email->value()),
            [
                'action' => $this->action($alert),
                'occurredAt' => $occurredAt->format('Y-m-d H:i T'),
            ],
        );

        $this->emails->send($message);
    }

    private function action(SecurityAlert $alert): string
    {
        return match ($alert) {
            SecurityAlert::PasswordChanged => 'Your password was changed.',
            SecurityAlert::PasswordReset => 'Your password was reset and all devices were signed out.',
            SecurityAlert::MultiFactorEnabled => 'Multi-factor authentication was enabled.',
            SecurityAlert::MultiFactorDisabled => 'Multi-factor authentication was disabled.',
            SecurityAlert::RecoveryCodesRegenerated => 'Your MFA recovery codes were replaced.',
            SecurityAlert::HighValueTransferApproved => 'A high-value transfer request received MFA approval.',
        };
    }
}
