<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Notification\Application\Email\EmailSender;
use Notification\Application\Email\EmailTransport;
use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Email\ValueObject\EmailBody;
use Notification\Domain\Email\ValueObject\EmailSubject;
use Notification\Domain\Email\ValueObject\RecipientEmail;
use Notification\Domain\Outbox\EmailOutboxMessage;
use Notification\Domain\Outbox\Repository\EmailOutboxRepository;
use Notification\Infrastructure\Outbox\DatabaseEmailOutboxRepository;
use Notification\Infrastructure\Outbox\OutboxEmailSender;
use Notification\Infrastructure\Outbox\ProcessEmailOutboxJob;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function outboxTestEmail(): EmailMessage
{
    return new EmailMessage(
        new RecipientEmail('outbox@example.com'),
        new EmailSubject('Durable email'),
        new EmailBody('Secret email contents.'),
    );
}

it('binds email sending and outbox storage to their durable adapters', function (): void {
    expect(app(EmailSender::class))->toBeInstanceOf(OutboxEmailSender::class)
        ->and(app(EmailOutboxRepository::class))->toBeInstanceOf(DatabaseEmailOutboxRepository::class);
});

it('stores encrypted email content and reconstructs it for delivery', function (): void {
    app(EmailSender::class)->send(outboxTestEmail());
    $storedPayload = (string) DB::table('email_outbox')->value('encrypted_payload');
    $pending = app(EmailOutboxRepository::class)->pending(10);

    expect($storedPayload)->not->toContain('outbox@example.com')
        ->and($storedPayload)->not->toContain('Secret email contents')
        ->and($pending)->toHaveCount(1)
        ->and($pending[0]->email()->recipient()->value())->toBe('outbox@example.com')
        ->and($pending[0]->email()->body()->value())->toBe('Secret email contents.');

    app(EmailOutboxRepository::class)->markDelivered($pending[0]->id(), new DateTimeImmutable);

    expect(app(EmailOutboxRepository::class)->pending(10))->toBeEmpty()
        ->and(DB::table('email_outbox')->value('attempts'))->toBe(1)
        ->and(DB::table('email_outbox')->value('delivered_at'))->not->toBeNull();
});

it('delivers pending emails and marks each successful message', function (): void {
    $message = new EmailOutboxMessage(Uuid::generate(), outboxTestEmail(), new DateTimeImmutable('2026-09-25T10:00:00+01:00'));
    $outbox = Mockery::mock(EmailOutboxRepository::class);
    $outbox->shouldReceive('pending')->once()->with(50)->andReturn([$message]);
    $outbox->shouldReceive('markDelivered')->once()->with($message->id(), Mockery::type(DateTimeImmutable::class));
    $transport = Mockery::mock(EmailTransport::class);
    $transport->shouldReceive('deliver')->once()->with($message->email());
    $clock = Mockery::mock(Clock::class);
    $clock->shouldReceive('now')->once()->andReturn(new DateTimeImmutable('2026-09-25T10:01:00+01:00'));
    $job = new ProcessEmailOutboxJob;

    $job->handle($outbox, $transport, $clock);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->queue)->toBe('notifications')
        ->and($job->afterCommit)->toBeTrue();
});

it('records a safe failure and continues without losing the outbox message', function (): void {
    $message = new EmailOutboxMessage(Uuid::generate(), outboxTestEmail(), new DateTimeImmutable('2026-09-25T10:00:00+01:00'));
    $outbox = Mockery::mock(EmailOutboxRepository::class);
    $outbox->shouldReceive('pending')->once()->andReturn([$message]);
    $outbox->shouldReceive('recordFailure')->once()->with($message->id(), Mockery::type(DateTimeImmutable::class));
    $transport = Mockery::mock(EmailTransport::class);
    $transport->shouldReceive('deliver')->once()->andThrow(new RuntimeException('SMTP credential leaked here'));
    $clock = Mockery::mock(Clock::class);
    $clock->shouldReceive('now')->once()->andReturn(new DateTimeImmutable('2026-09-25T10:01:00+01:00'));

    (new ProcessEmailOutboxJob)->handle($outbox, $transport, $clock);
});
