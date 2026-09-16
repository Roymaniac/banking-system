<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Event;

use Illuminate\Contracts\Events\Dispatcher;
use Shared\Contracts\EventPublisher;

/**
 * Sends each domain event to Laravel so registered listeners can react to it.
 */
final readonly class LaravelEventPublisher implements EventPublisher
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
