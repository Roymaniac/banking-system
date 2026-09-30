<?php

declare(strict_types=1);

namespace Administration\Application\Role;

use Administration\Domain\Role\Exception\RoleAlreadyAssigned;
use Administration\Domain\Role\Exception\RoleNotFound;
use Administration\Domain\Role\Repository\RoleAssignmentRepository;
use Administration\Domain\Role\Repository\RoleRepository;
use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Staff\Exception\InactiveStaffCannotChange;
use Administration\Domain\Staff\Exception\StaffNotFound;
use Administration\Domain\Staff\Repository\StaffRepository;
use Administration\Domain\Staff\ValueObject\StaffId;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\UuidGenerator;

/** Assigns an active role to active staff exactly once. */
final readonly class AssignRoleToStaff
{
    public function __construct(
        private RoleRepository $roles,
        private RoleAssignmentRepository $assignments,
        private StaffRepository $staff,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(
        RoleId $roleId,
        StaffId $staffId,
        ?CorrelationId $correlationId = null
    ): void {

        $events = $this->transactions->run(
            function () use ($roleId, $staffId, $correlationId): array {

                $role = $this->roles->findByIdForUpdate($roleId);

                if ($role === null) {
                    throw RoleNotFound::create();
                }

                $staff = $this->staff->findByIdForUpdate($staffId);

                if ($staff === null) {
                    throw StaffNotFound::create();
                }

                if ($staff->status() !== StaffStatus::Active) {
                    throw InactiveStaffCannotChange::create();
                }

                if ($this->assignments->exists($roleId, $staffId)) {
                    throw RoleAlreadyAssigned::create();
                }

                $at = $this->clock->now();

                $role->recordAssignment(
                    $staffId,
                    $at,
                    $this->ids->generate(),
                    $correlationId
                );

                $this->roles->save($role);

                $this->assignments->add(
                    $roleId,
                    $staffId,
                    $at
                );

                return $role->pullDomainEvents();
            }
        );

        $this->events->publish($events);
    }
}
