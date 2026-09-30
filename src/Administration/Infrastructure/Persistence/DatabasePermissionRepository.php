<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Persistence;

use Administration\Domain\Permission\PermissionDefinition;
use Administration\Domain\Permission\Repository\PermissionRepository;
use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Permission\ValueObject\PermissionLabel;
use Administration\Domain\Permission\ValueObject\PermissionName;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabasePermissionRepository implements PermissionRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(PermissionDefinition $permission): void
    {
        $this->connection->table('administration_permissions')
            ->insert([
                'id' => $permission->id()->value(),
                'name' => $permission->name()->value,
                'label' => $permission->label()->value,
                'created_at' => $permission->createdAt()->setTimezone(new DateTimeZone('UTC')),
                'version' => $permission->version(),
            ]);
    }

    public function findById(PermissionId $id): ?PermissionDefinition
    {
        $p = $this->connection->table('administration_permissions')
            ->where('id', $id->value())
            ->first();

        return $p === null ? null : PermissionDefinition::reconstitute(
            new PermissionId($p->id),
            new PermissionName($p->name),
            new PermissionLabel($p->label),
            new DateTimeImmutable($p->created_at),
            (int) $p->version
        );
    }

    public function nameExists(PermissionName $name): bool
    {
        return $this->connection->table('administration_permissions')
            ->where('name', $name->value)
            ->exists();
    }
}
