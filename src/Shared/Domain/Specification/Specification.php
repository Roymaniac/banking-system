<?php

declare(strict_types=1);

namespace Shared\Domain\Specification;

/**
 * A reusable business rule that can answer whether a candidate is acceptable.
 *
 * Examples include: “a customer has completed KYC” or “an account can receive
 * a transfer”. Keeping these rules in dedicated objects prevents them from
 * being duplicated across controllers, services, and database queries.
 */
interface Specification
{
    /**
     * @param  TCandidate  $candidate
     */
    public function isSatisfiedBy(mixed $candidate): bool;
}
