<?php

declare(strict_types=1);

namespace Administration\Application\Staff;

use Administration\Domain\Department\Repository\DepartmentRepository;
use Administration\Domain\Department\ValueObject\DepartmentStatus;
use Administration\Domain\Staff\Exception\ActiveDepartmentRequired;
use Administration\Domain\Staff\Exception\StaffAlreadyExists;
use Administration\Domain\Staff\Repository\StaffRepository;
use Administration\Domain\Staff\Staff;
use Administration\Domain\Staff\ValueObject\EmployeeNumber;
use Administration\Domain\Staff\ValueObject\JobTitle;
use Administration\Domain\Staff\ValueObject\StaffId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Onboards staff only into an active department. */
final readonly class HireStaff
{
    public function __construct(
        private DepartmentRepository $departments,
        private StaffRepository $staff,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(HireStaffCommand $command): Staff
    {
        $number = new EmployeeNumber($command->employeeNumber);
        $department = $this->departments->findById($command->departmentId);
        if ($department === null || $department->status() !== DepartmentStatus::Active) {
            throw ActiveDepartmentRequired::create();
        }
        if ($this->staff->employeeNumberExists($number) || $this->staff->userExists($command->userId)) {
            throw StaffAlreadyExists::create();
        }

        $staff = Staff::hire(
            new StaffId($this->ids->generate()->value()),
            $command->userId,
            $number,
            $command->departmentId,
            new JobTitle($command->jobTitle),
            $this->clock->now(),
            $this->ids->generate(),
            $command->correlationId
        );

        $events = $this->transactions->run(function () use ($staff): array {
            $this->staff->save($staff);

            return $staff->pullDomainEvents();
        });
        $this->events->publish($events);

        return $staff;
    }
}
