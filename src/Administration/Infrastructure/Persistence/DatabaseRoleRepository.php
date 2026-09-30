<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Persistence;

use Administration\Domain\Role\Repository\RoleRepository;
use Administration\Domain\Role\Role;
use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Role\ValueObject\RoleLabel;
use Administration\Domain\Role\ValueObject\RoleName;
use Administration\Domain\Role\ValueObject\RoleStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Domain\Exception\ConcurrencyException;

final readonly class DatabaseRoleRepository implements RoleRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Role $role): void
    {
        $stored = $this->connection->table('administration_roles')
            ->where('id', $role->id()->value())
            ->value('version');

        $values = [
            'name' => $role->name()->value,
            'label' => $role->label()->value,
            'status' => $role->status()->value,
            'created_at' => $role->createdAt()->setTimezone(new DateTimeZone('UTC')),
            'deactivated_at' => $role->deactivatedAt()?->setTimezone(new DateTimeZone('UTC')),
            'version' => $role->version()
        ];

        if ($stored === null) {
            $this->connection->table('administration_roles')
                ->insert([
                    'id' => $role->id()->value(),
                    ...$values
                ]);

            return;
        }
        $expected = $role->version() - 1;
        $updated = $this->connection->table('administration_roles')
            ->where('id', $role->id()->value())
            ->where('version', $expected)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($role->id(), $expected, (int) $stored);
        }
    }

    public function findByIdForUpdate(RoleId $id): ?Role
    {
        $r = $this->connection->table('administration_roles')
            ->where('id', $id->value())
            ->lockForUpdate()
            ->first();

        if ($r === null) {
            return null;
        }

        return Role::reconstitute(
            new RoleId($r->id),
            new RoleName($r->name),
            new RoleLabel($r->label),
            new DateTimeImmutable($r->created_at),
            RoleStatus::from($r->status),
            $r->deactivated_at === null ? null : new DateTimeImmutable($r->deactivated_at),
            (int) $r->version
        );
    }

    public function nameExists(RoleName $name): bool
    {
        return $this->connection->table('administration_roles')
            ->where('name', $name->value)
            ->exists();
    }
}
