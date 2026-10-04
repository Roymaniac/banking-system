<?php

declare(strict_types=1);

namespace Notification\Application\Outbox;

use Notification\Application\Outbox\Exception\EmailOutboxMessageCannotRetry;
use Notification\Application\Outbox\Exception\EmailOutboxMessageNotFound;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;

/** Requeues only messages whose automatic delivery attempts are exhausted. */
final readonly class RetryExhaustedEmail
{
    public function __construct(
        private EmailOutboxRetryGateway $gateway,
        private Clock $clock,
    ) {}

    public function handle(Uuid $id): void
    {
        match ($this->gateway->retry($id, $this->clock->now())) {
            EmailOutboxRetryResult::Requeued => null,
            EmailOutboxRetryResult::NotFound => throw EmailOutboxMessageNotFound::create(),
            EmailOutboxRetryResult::StillPending => throw EmailOutboxMessageCannotRetry::stillPending(),
            EmailOutboxRetryResult::Delivered => throw EmailOutboxMessageCannotRetry::delivered(),
        };
    }
}
