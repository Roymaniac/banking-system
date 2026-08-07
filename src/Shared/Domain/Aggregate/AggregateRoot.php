<?php

declare(strict_types=1);

namespace Shared\Domain\Aggregate;

use Shared\Domain\Entity\Entity;
use Shared\Domain\Event\RecordDomainEvents;

abstract class AggregateRoot extends Entity
{
    use RecordDomainEvents;
}
