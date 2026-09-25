<?php

declare(strict_types=1);

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Notification\Application\Email\EmailSender;
use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Email\ValueObject\EmailBody;
use Notification\Domain\Email\ValueObject\EmailSubject;
use Notification\Domain\Email\ValueObject\RecipientEmail;
use Notification\Infrastructure\Email\Exception\EmailDeliveryFailed;
use Notification\Infrastructure\Email\LaravelEmailSender;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

uses(TestCase::class);

function notificationTestEmail(): EmailMessage
{
    return new EmailMessage(
        new RecipientEmail('customer@example.com'),
        new EmailSubject('Security notice'),
        new EmailBody('A new sign-in was detected.'),
    );
}

it('binds the email sender contract to the Laravel adapter', function (): void {
    expect(app(EmailSender::class))->toBeInstanceOf(LaravelEmailSender::class);
});

it('maps the email message to Laravel mail correctly', function (): void {
    $mailer = Mockery::mock(Mailer::class);
    $mailer->shouldReceive('raw')->once()->andReturnUsing(function (string $body, callable $configure): void {
        $symfonyEmail = new Email;
        $message = new Message($symfonyEmail);
        $configure($message);

        expect($body)->toBe('A new sign-in was detected.')
            ->and($symfonyEmail->getTo()[0]->getAddress())->toBe('customer@example.com')
            ->and($symfonyEmail->getSubject())->toBe('Security notice');
    });

    (new LaravelEmailSender($mailer))->send(notificationTestEmail());
});

it('wraps transport errors without exposing message contents', function (): void {
    $mailer = Mockery::mock(Mailer::class);
    $mailer->shouldReceive('raw')->once()->andThrow(new RuntimeException('SMTP password rejected'));

    try {
        (new LaravelEmailSender($mailer))->send(notificationTestEmail());
    } catch (EmailDeliveryFailed $exception) {
        expect($exception->getMessage())->not->toContain('SMTP password')
            ->and($exception->getMessage())->not->toContain('customer@example.com')
            ->and($exception->getPrevious())->toBeInstanceOf(RuntimeException::class);

        return;
    }

    test()->fail('Expected email delivery to fail.');
});
