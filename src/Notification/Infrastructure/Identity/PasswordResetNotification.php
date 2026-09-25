<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Identity;

use DateTimeImmutable;
use Identity\Application\PasswordReset\PasswordResetNotifier;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\User\ValueObject\EmailAddress;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Notification\Application\Email\EmailSender;
use Notification\Application\Template\EmailTemplate;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Domain\Email\ValueObject\RecipientEmail;

/** Builds and queues Identity's password-reset message. */
final readonly class PasswordResetNotification implements PasswordResetNotifier
{
    public function __construct(
        private EmailTemplateRenderer $templates,
        private EmailSender $emails,
        private ConfigRepository $config,
    ) {}

    public function send(EmailAddress $email, PasswordResetToken $token, DateTimeImmutable $expiresAt): void
    {
        $baseUrl = rtrim((string) $this->config->get('notification.password_reset_url'), '/');
        $message = $this->templates->render(
            EmailTemplate::PasswordReset,
            new RecipientEmail($email->value()),
            [
                'resetUrl' => $baseUrl.'?token='.rawurlencode($token->value()),
                'expiresAt' => $expiresAt->format('Y-m-d H:i T'),
            ],
        );

        $this->emails->send($message);
    }
}
