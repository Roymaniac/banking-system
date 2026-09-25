<?php

declare(strict_types=1);

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Notification\Application\Email\EmailSender;
use Notification\Application\Email\EmailTransport;
use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Email\ValueObject\EmailBody;
use Notification\Domain\Email\ValueObject\EmailSubject;
use Notification\Domain\Email\ValueObject\RecipientEmail;
use Notification\Infrastructure\Queue\QueuedEmailSender;
use Notification\Infrastructure\Queue\SendEmailJob;
use Tests\TestCase;

uses(TestCase::class);

function queuedNotificationTestEmail(): EmailMessage
{
    return new EmailMessage(
        new RecipientEmail('queued@example.com'),
        new EmailSubject('Queued notice'),
        new EmailBody('This message is delivered by a worker.'),
    );
}

it('binds normal email sending to the queued adapter', function (): void {
    expect(app(EmailSender::class))->toBeInstanceOf(QueuedEmailSender::class);
});

it('dispatches an encrypted email job after database commit', function (): void {
    $email = queuedNotificationTestEmail();
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->with(Mockery::on(function (SendEmailJob $job) use ($email): bool {
        return $job->email === $email
            && $job instanceof ShouldQueue
            && $job instanceof ShouldBeEncrypted
            && $job->queue === 'notifications'
            && $job->afterCommit === true;
    }));

    (new QueuedEmailSender($dispatcher))->send($email);
});

it('delivers the queued message through the immediate transport', function (): void {
    $email = queuedNotificationTestEmail();
    $transport = Mockery::mock(EmailTransport::class);
    $transport->shouldReceive('deliver')->once()->with($email);
    $job = new SendEmailJob($email);

    $job->handle($transport);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([60, 300, 900, 1800]);
});
