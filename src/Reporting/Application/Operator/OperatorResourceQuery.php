<?php

declare(strict_types=1);

namespace Reporting\Application\Operator;

/** Provides bounded, privacy-safe records for operator resource selectors. */
interface OperatorResourceQuery
{
    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>} */
    public function accounts(array $filters): array;

    /** @return array<string, mixed>|null */
    public function account(string $id, string $purpose): ?array;

    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>} */
    public function customers(array $filters): array;

    /** @return array<string, mixed>|null */
    public function customer(string $id): ?array;

    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>} */
    public function staffCandidates(array $filters): array;

    /** @return array<string, mixed>|null */
    public function staffCandidate(string $userId): ?array;

    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>} */
    public function ledgerEntries(array $filters): array;

    /** @return array<string, mixed>|null */
    public function ledgerEntry(string $id): ?array;
}
