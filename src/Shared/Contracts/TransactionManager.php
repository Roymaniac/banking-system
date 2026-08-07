<?php

namespace Shared\Contracts;

interface TransactionManager
{
    public function run(callable $callback): mixed;
}
