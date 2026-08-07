<?php

namespace Shared\Contracts;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
