<?php

declare(strict_types=1);

namespace Administration\Application\Department;

use Administration\Domain\Department\Exception\DepartmentNotFound;
use Administration\Domain\Department\Repository\DepartmentRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Retires a department so new staff cannot be assigned to it. */
final readonly class DeactivateDepartment
{
    public function __construct(
        private DepartmentRepository $departments,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(DeactivateDepartmentCommand $command): void
    {
        $domainEvents = $this->transactions->run(function () use ($command): array {
            $department = $this->departments->findByIdForUpdate($command->departmentId);
            if ($department === null) {
                throw DepartmentNotFound::create();
            }

            $department->deactivate(
                $this->clock->now(),
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $this->departments->save($department);

            return $department->pullDomainEvents();
        });
        $this->events->publish($domainEvents);
    }
}
