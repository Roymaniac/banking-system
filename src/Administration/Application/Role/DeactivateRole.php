<?php

declare(strict_types=1);

namespace Administration\Application\Role;

use Administration\Domain\Role\Exception\RoleNotFound;
use Administration\Domain\Role\Repository\RoleRepository;
use Administration\Domain\Role\ValueObject\RoleId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class DeactivateRole
{
    public function __construct(
        private RoleRepository $roles,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(
        RoleId $id,
        ?CorrelationId $correlationId = null
    ): void {

        $events = $this->transactions->run(
            function () use ($id, $correlationId): array {

                $role = $this->roles->findByIdForUpdate($id);

                if ($role === null) {
                    throw RoleNotFound::create();
                }

                $role->deactivate(
                    $this->clock->now(),
                    $this->ids->generate(),
                    $correlationId
                );

                $this->roles->save($role);

                return $role->pullDomainEvents();
            }
        );

        $this->events->publish($events);
    }
}
