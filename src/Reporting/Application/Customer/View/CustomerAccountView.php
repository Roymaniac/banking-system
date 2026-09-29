<?php

declare(strict_types=1);

namespace Reporting\Application\Customer\View;

use DateTimeImmutable;

/** Read-only account summary included in a customer report. */
final readonly class CustomerAccountView
{
    public function __construct(
        public string $id,
        public ?string $number,
        public string $type,
        public string $currency,
        public string $status,
        public DateTimeImmutable $openedOn,
    ) {}
}
