<?php

declare(strict_types=1);

namespace Reporting\Application\Customer\View;

/** Read-only contact data prepared for a customer report. */
final readonly class CustomerContactView
{
    public function __construct(
        public string $type,
        public string $value,
    ) {}
}
