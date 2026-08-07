<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

interface DomainEvent
{
    public function eventName(): string;
    public function occurredOn(): \DateTimeImmutable;
}
