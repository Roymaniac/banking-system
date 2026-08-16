<?php

declare(strict_types=1);

namespace Shared\Domain\Repository;

use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\Identifier;

interface AggregateRepository
{

    public function save(AggregateRoot $aggregate): void;

    public function find(Identifier $id): ?AggregateRoot;

    public function exists(Identifier $id): bool;
}
