<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Event;

use Administration\Domain\Role\ValueObject\RoleId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

final readonly class RoleDeactivated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        RoleId $id,
        int $version,
        DateTimeImmutable $at,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct(
            $eventId,
            $id,
            $version,
            $at,
            $correlationId
        );
    }

    public static function eventName(): string
    {
        return 'administration.role_deactivated';
    }
}
