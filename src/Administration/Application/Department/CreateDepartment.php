<?php

declare(strict_types=1);

namespace Administration\Application\Department;

use Administration\Domain\Department\Department;
use Administration\Domain\Department\Exception\DepartmentCodeAlreadyExists;
use Administration\Domain\Department\Repository\DepartmentRepository;
use Administration\Domain\Department\ValueObject\DepartmentCode;
use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Department\ValueObject\DepartmentName;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Creates a uniquely coded department and publishes its creation event. */
final readonly class CreateDepartment
{
    public function __construct(
        private DepartmentRepository $departments,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(CreateDepartmentCommand $command): Department
    {
        $code = new DepartmentCode($command->code);

        if ($this->departments->codeExists($code)) {
            throw DepartmentCodeAlreadyExists::forCode($code);
        }

        $department = Department::create(
            new DepartmentId($this->uuidGenerator->generate()->value()),
            $code,
            new DepartmentName($command->name),
            $this->clock->now(),
            $this->uuidGenerator->generate(),
            $command->correlationId
        );

        $domainEvents = $this->transactions->run(function () use ($department): array {
            $this->departments->save($department);

            return $department->pullDomainEvents();
        });

        $this->events->publish($domainEvents);

        return $department;
    }
}
