<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Identity;

use DateTimeImmutable;
use Identity\Application\EmailVerification\EmailVerificationNotifier;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;
use Identity\Domain\User\ValueObject\EmailAddress;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Notification\Application\Email\EmailSender;
use Notification\Application\Template\EmailTemplate;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Domain\Email\ValueObject\RecipientEmail;

/** Builds and queues Identity's email-verification message. */
final readonly class EmailVerificationNotification implements EmailVerificationNotifier
{
    public function __construct(
        private EmailTemplateRenderer $templates,
        private EmailSender $emails,
        private ConfigRepository $config,
    ) {}

    public function send(EmailAddress $email, EmailVerificationToken $token, DateTimeImmutable $expiresAt): void
    {
        $baseUrl = rtrim((string) $this->config->get('notification.verification_url'), '/');
        $message = $this->templates->render(
            EmailTemplate::EmailVerification,
            new RecipientEmail($email->value()),
            [
                'verificationUrl' => $baseUrl.'?token='.rawurlencode($token->value()),
                'expiresAt' => $expiresAt->format('Y-m-d H:i T'),
            ],
        );

        $this->emails->send($message);
    }
}
