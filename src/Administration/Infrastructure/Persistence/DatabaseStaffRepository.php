<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Persistence;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Staff\Repository\StaffRepository;
use Administration\Domain\Staff\Staff;
use Administration\Domain\Staff\ValueObject\EmployeeNumber;
use Administration\Domain\Staff\ValueObject\JobTitle;
use Administration\Domain\Staff\ValueObject\StaffId;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use DateTimeImmutable;
use DateTimeZone;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;
use Shared\Domain\Exception\ConcurrencyException;

/** Stores staff records with optimistic locking. */
final readonly class DatabaseStaffRepository implements StaffRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Staff $staff): void
    {
        $storedVersion = $this->connection->table('staff')
            ->where('id', $staff->id()->value())
            ->value('version');

        $values = [
            'user_id' => $staff->userId()->value(),
            'employee_number' => $staff->employeeNumber()->value,
            'department_id' => $staff->departmentId()->value(),
            'job_title' => $staff->jobTitle()->value,
            'status' => $staff->status()->value,
            'hired_at' => $staff->hiredAt()->setTimezone(new DateTimeZone('UTC')),
            'deactivated_at' => $staff->deactivatedAt()?->setTimezone(new DateTimeZone('UTC')),
            'version' => $staff->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('staff')
                ->insert(['id' => $staff->id()->value(), ...$values]);

            return;
        }

        $expected = $staff->version() - 1;

        $updated = $this->connection->table('staff')
            ->where('id', $staff->id()->value())
            ->where('version', $expected)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($staff->id(), $expected, (int) $storedVersion);
        }
    }

    public function findByIdForUpdate(StaffId $id): ?Staff
    {
        return $this->hydrate($this->connection->table('staff')
            ->where('id', $id->value())
            ->lockForUpdate()
            ->first());
    }

    public function employeeNumberExists(EmployeeNumber $number): bool
    {
        return $this->connection->table('staff')
            ->where('employee_number', $number->value)
            ->exists();
    }

    public function userExists(UserId $userId): bool
    {
        return $this->connection->table('staff')
            ->where('user_id', $userId->value())
            ->exists();
    }

    private function hydrate(?object $record): ?Staff
    {
        if ($record === null) {
            return null;
        }

        return Staff::reconstitute(
            new StaffId($record->id),
            new UserId($record->user_id),
            new EmployeeNumber($record->employee_number),
            new DepartmentId($record->department_id),
            new JobTitle($record->job_title),
            new DateTimeImmutable($record->hired_at),
            StaffStatus::from($record->status),
            $record->deactivated_at === null ? null : new DateTimeImmutable($record->deactivated_at),
            (int) $record->version
        );
    }
}
