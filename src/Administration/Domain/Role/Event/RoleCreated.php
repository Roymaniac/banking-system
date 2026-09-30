<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Event;

use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Role\ValueObject\RoleLabel;
use Administration\Domain\Role\ValueObject\RoleName;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

final readonly class RoleCreated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        RoleId $id,
        DateTimeImmutable $at,
        private RoleName $name,
        private RoleLabel $label,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct(
            $eventId,
            $id,
            1,
            $at,
            $correlationId
        );
    }

    public static function eventName(): string
    {
        return 'administration.role_created';
    }

    public function payload(): array
    {
        return [
            'name' => $this->name->value(),
            'label' => $this->label->value()
        ];
    }
}
