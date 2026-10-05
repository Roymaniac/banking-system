<?php

declare(strict_types=1);

namespace Administration\Application\Directory;

/** Provides read-only administration views without loading writable aggregates. */
interface AdministrationDirectoryQuery
{
    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>} */
    public function departments(int $page, int $perPage, ?string $search, ?string $status): array;

    /** @return array<string, mixed>|null */
    public function department(string $id): ?array;

    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>} */
    public function staff(int $page, int $perPage, ?string $search, ?string $status): array;

    /** @return array<string, mixed>|null */
    public function staffMember(string $id): ?array;

    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>} */
    public function roles(int $page, int $perPage, ?string $search, ?string $status): array;

    /** @return array<string, mixed>|null */
    public function role(string $id): ?array;

    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>} */
    public function permissions(int $page, int $perPage, ?string $search): array;

    /** @return array<string, mixed>|null */
    public function permission(string $id): ?array;
}
