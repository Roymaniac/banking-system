<?php

declare(strict_types=1);

namespace Shared\Domain\Aggregate;

use Shared\Domain\Event\RecordDomainEvents;

abstract class AggregateRoot
{
    use RecordDomainEvents;
}
