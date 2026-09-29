<?php

declare(strict_types=1);

namespace Administration\Application\Department;

use Administration\Domain\Department\Exception\DepartmentNotFound;
use Administration\Domain\Department\Repository\DepartmentRepository;
use Administration\Domain\Department\ValueObject\DepartmentName;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Renames an active department while holding its database row lock. */
final readonly class RenameDepartment
{
    public function __construct(
        private DepartmentRepository $departments,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(RenameDepartmentCommand $command): void
    {
        $domainEvents = $this->transactions->run(function () use ($command): array {
            $department = $this->departments->findByIdForUpdate($command->departmentId);
            if ($department === null) {
                throw DepartmentNotFound::create();
            }

            $department->rename(
                new DepartmentName($command->name),
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
