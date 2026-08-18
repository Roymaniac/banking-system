<?php

declare(strict_types=1);

namespace Shared\Contracts;

interface TransactionManager
{
    /**
     * Runs work as one all-or-nothing database operation.
     */
    public function run(callable $callback): mixed;
}
