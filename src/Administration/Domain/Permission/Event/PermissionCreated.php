<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\Event;

use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Permission\ValueObject\PermissionLabel;
use Administration\Domain\Permission\ValueObject\PermissionName;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

final readonly class PermissionCreated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        PermissionId $id,
        DateTimeImmutable $at,
        private PermissionName $name,
        private PermissionLabel $label,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct($eventId, $id, 1, $at, $correlationId);
    }

    public static function eventName(): string
    {
        return 'administration.permission_created';
    }

    public function payload(): array
    {
        return [
            'name' => $this->name->value,
            'label' => $this->label->value,
        ];
    }
}
