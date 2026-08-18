<?php

declare(strict_types=1);

namespace Shared\Contracts;

use Shared\Domain\Event\DomainEvent;

interface EventPublisher
{
    /**
     * Publishes domain events after a successful business operation.
     *
     * @param  list<DomainEvent>  $events
     */
    public function publish(array $events): void;
}
