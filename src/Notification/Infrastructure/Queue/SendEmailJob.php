<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Notification\Application\Email\EmailTransport;
use Notification\Domain\Email\EmailMessage;

/** Delivers one encrypted email payload from the notification queue. */
final class SendEmailJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Stop retrying after repeated transport failures. */
    public int $tries = 5;

    public function __construct(public readonly EmailMessage $email)
    {
        $this->onQueue('notifications');
        // A job must not run if the database work that requested it rolls back.
        $this->afterCommit();
    }

    /** @return list<int> Seconds to wait before each later retry. */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(EmailTransport $transport): void
    {
        $transport->deliver($this->email);
    }
}
