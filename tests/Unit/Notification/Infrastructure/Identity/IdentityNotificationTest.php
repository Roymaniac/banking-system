<?php

declare(strict_types=1);

use Identity\Application\EmailVerification\EmailVerificationNotifier;
use Identity\Application\PasswordReset\PasswordResetNotifier;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\User\ValueObject\EmailAddress;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Notification\Application\Email\EmailSender;
use Notification\Application\Template\EmailTemplate;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Email\ValueObject\EmailBody;
use Notification\Domain\Email\ValueObject\EmailSubject;
use Notification\Domain\Email\ValueObject\RecipientEmail;
use Notification\Infrastructure\Identity\EmailVerificationNotification;
use Notification\Infrastructure\Identity\PasswordResetNotification;
use Tests\TestCase;

uses(TestCase::class);

function renderedIdentityTestEmail(string $recipient): EmailMessage
{
    return new EmailMessage(
        new RecipientEmail($recipient),
        new EmailSubject('Identity notice'),
        new EmailBody('Secure identity message.')
    );
}

it('binds both Identity notifier contracts to email template adapters', function (): void {
    expect(app(EmailVerificationNotifier::class))->toBeInstanceOf(EmailVerificationNotification::class)
        ->and(app(PasswordResetNotifier::class))->toBeInstanceOf(PasswordResetNotification::class);
});

it('renders and queues an email-verification link', function (): void {
    $token = str_repeat('a', 64);
    $rendered = renderedIdentityTestEmail('verify@example.com');
    $templates = Mockery::mock(EmailTemplateRenderer::class);
    $templates->shouldReceive('render')->once()->with(
        EmailTemplate::EmailVerification,
        Mockery::on(fn(RecipientEmail $email): bool => $email->value() === 'verify@example.com'),
        Mockery::on(fn(array $variables): bool => $variables['verificationUrl'] === 'https://bank.example/verify-email?token=' . $token && isset($variables['expiresAt'])),
    )->andReturn($rendered);
    $emails = Mockery::mock(EmailSender::class);
    $emails->shouldReceive('send')->once()->with($rendered);
    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('get')->once()->with('notification.verification_url')->andReturn('https://bank.example/verify-email');

    (new EmailVerificationNotification($templates, $emails, $config))->send(
        new EmailAddress('verify@example.com'),
        new EmailVerificationToken($token),
        new DateTimeImmutable('2026-09-25T12:00:00+01:00'),
    );
});

it('renders and queues a password-reset link', function (): void {
    $token = str_repeat('b', 64);
    $rendered = renderedIdentityTestEmail('reset@example.com');
    $templates = Mockery::mock(EmailTemplateRenderer::class);
    $templates->shouldReceive('render')->once()->with(
        EmailTemplate::PasswordReset,
        Mockery::on(fn(RecipientEmail $email): bool => $email->value() === 'reset@example.com'),
        Mockery::on(fn(array $variables): bool => $variables['resetUrl'] === 'https://bank.example/reset-password?token=' . $token && isset($variables['expiresAt'])),
    )->andReturn($rendered);
    $emails = Mockery::mock(EmailSender::class);
    $emails->shouldReceive('send')->once()->with($rendered);
    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('get')->once()->with('notification.password_reset_url')->andReturn('https://bank.example/reset-password');

    (new PasswordResetNotification($templates, $emails, $config))->send(
        new EmailAddress('reset@example.com'),
        new PasswordResetToken($token),
        new DateTimeImmutable('2026-09-25T12:00:00+01:00'),
    );
});
