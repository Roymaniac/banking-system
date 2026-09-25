<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Outbox;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Notification\Application\Email\EmailTransport;
use Notification\Domain\Outbox\Repository\EmailOutboxRepository;
use Shared\Contracts\Clock;
use Throwable;

/** Delivers a small batch of durable emails and records each outcome. */
final class ProcessEmailOutboxJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Prevent overlapping workers from sending the same pending email. */
    public int $uniqueFor = 55;

    public function __construct()
    {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(EmailOutboxRepository $outbox, EmailTransport $transport, Clock $clock): void
    {
        foreach ($outbox->pending(50) as $message) {
            try {
                $transport->deliver($message->email());
                $outbox->markDelivered($message->id(), $clock->now());
            } catch (Throwable) {
                // A later scheduled run retries this message. Other emails in
                // the batch can still be delivered during the current run.
                $outbox->recordFailure($message->id(), $clock->now());
            }
        }
    }
}
