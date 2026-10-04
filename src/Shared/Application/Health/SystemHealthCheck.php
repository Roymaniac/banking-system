<?php

declare(strict_types=1);

namespace Shared\Application\Health;

interface SystemHealthCheck
{
    public function inspect(): SystemHealthReport;
}
