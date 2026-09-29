<?php

declare(strict_types=1);

namespace Administration\Application\Staff;

use Administration\Domain\Department\Repository\DepartmentRepository;
use Administration\Domain\Department\ValueObject\DepartmentStatus;
use Administration\Domain\Staff\Exception\ActiveDepartmentRequired;
use Administration\Domain\Staff\Exception\StaffNotFound;
use Administration\Domain\Staff\Repository\StaffRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Moves active staff to another active department. */
final readonly class TransferStaff
{
    public function __construct(
        private DepartmentRepository $departments,
        private StaffRepository $staff,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(TransferStaffCommand $command): void
    {
        $department = $this->departments->findById($command->departmentId);
        if ($department === null || $department->status() !== DepartmentStatus::Active) {
            throw ActiveDepartmentRequired::create();
        }
        $events = $this->transactions->run(function () use ($command): array {
            $staff = $this->staff->findByIdForUpdate($command->staffId);
            if ($staff === null) {
                throw StaffNotFound::create();
            }

            $staff->transfer(
                $command->departmentId,
                $this->clock->now(),
                $this->ids->generate(),
                $command->correlationId
            );

            $this->staff->save($staff);

            return $staff->pullDomainEvents();
        });
        $this->events->publish($events);
    }
}
