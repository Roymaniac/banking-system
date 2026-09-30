<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Persistence;

use Administration\Domain\Department\Department;
use Administration\Domain\Department\Repository\DepartmentRepository;
use Administration\Domain\Department\ValueObject\DepartmentCode;
use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Department\ValueObject\DepartmentName;
use Administration\Domain\Department\ValueObject\DepartmentStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Domain\Exception\ConcurrencyException;

/** Stores departments while protecting concurrent administrative updates. */
final readonly class DatabaseDepartmentRepository implements DepartmentRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Department $department): void
    {
        $storedVersion = $this->connection->table('departments')
            ->where('id', $department->id()->value())
            ->value('version');

        $values = [
            'code' => $department->code()->value,
            'name' => $department->name()->value,
            'status' => $department->status()->value,
            'created_at' => $department->createdAt()->setTimezone(new DateTimeZone('UTC')),
            'deactivated_at' => $department->deactivatedAt()?->setTimezone(new DateTimeZone('UTC')),
            'version' => $department->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('departments')
                ->insert(['id' => $department->id()->value(), ...$values]);

            return;
        }

        $expectedVersion = $department->version() - 1;
        $updated = $this->connection->table('departments')
            ->where('id', $department->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($department->id(), $expectedVersion, (int) $storedVersion);
        }
    }

    public function findById(DepartmentId $id): ?Department
    {
        return $this->hydrate($this->connection->table('departments')
            ->where('id', $id->value())
            ->first());
    }

    public function findByIdForUpdate(DepartmentId $id): ?Department
    {
        return $this->hydrate($this->connection->table('departments')
            ->where('id', $id->value())->lockForUpdate()
            ->first());
    }

    public function codeExists(DepartmentCode $code): bool
    {
        return $this->connection->table('departments')
            ->where('code', $code->value)
            ->exists();
    }

    private function hydrate(?object $record): ?Department
    {
        if ($record === null) {
            return null;
        }

        return Department::reconstitute(
            new DepartmentId($record->id),
            new DepartmentCode($record->code),
            new DepartmentName($record->name),
            new DateTimeImmutable($record->created_at),
            DepartmentStatus::from($record->status),
            $record->deactivated_at === null ? null : new DateTimeImmutable($record->deactivated_at),
            (int) $record->version,
        );
    }
}
