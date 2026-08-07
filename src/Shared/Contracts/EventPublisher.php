<?php

namespace Shared\Contracts;

interface EventPublisher
{
    public function publish(array $events): void;
}
