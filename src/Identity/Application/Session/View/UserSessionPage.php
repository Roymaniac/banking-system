<?php

declare(strict_types=1);

namespace Identity\Application\Session\View;

/** Contains one bounded page of a customer's active API sessions. */
final readonly class UserSessionPage
{
    /** @param list<UserSessionView> $sessions */
    public function __construct(
        public array $sessions,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}

    public function hasNextPage(): bool
    {
        return $this->page * $this->perPage < $this->total;
    }
}
