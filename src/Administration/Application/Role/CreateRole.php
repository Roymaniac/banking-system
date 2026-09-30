<?php

declare(strict_types=1);

namespace Administration\Application\Role;

use Administration\Domain\Role\Exception\RoleNameAlreadyExists;
use Administration\Domain\Role\Repository\RoleRepository;
use Administration\Domain\Role\Role;
use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Role\ValueObject\RoleLabel;
use Administration\Domain\Role\ValueObject\RoleName;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\UuidGenerator;

/** Creates a role whose permissions can be configured independently. */
final readonly class CreateRole
{
    public function __construct(
        private RoleRepository $roles,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(
        string $name,
        string $label,
        ?CorrelationId $correlationId = null
    ): Role {

        $roleName = new RoleName($name);

        if ($this->roles->nameExists($roleName)) {
            throw RoleNameAlreadyExists::create();
        }

        $role = Role::create(
            new RoleId($this->ids->generate()->value()),
            $roleName,
            new RoleLabel($label),
            $this->clock->now(),
            $this->ids->generate(),
            $correlationId
        );

        $events = $this->transactions->run(function () use ($role): array {
            $this->roles->save($role);

            return $role->pullDomainEvents();
        });

        $this->events->publish($events);

        return $role;
    }
}
