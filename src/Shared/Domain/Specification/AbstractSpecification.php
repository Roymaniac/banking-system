<?php

declare(strict_types=1);

namespace Shared\Domain\Specification;

/**
 * Base class for business rules that need to be combined with other rules.
 *
 * A concrete rule only needs to define its own condition. This class supplies
 * the readable “and”, “or”, and “not” operations for building larger rules.
 */
abstract class AbstractSpecification implements Specification
{
    /**
     * Requires both this rule and the supplied rule to pass.
     *
     * @return AndSpecification<TCandidate>
     */
    final public function and(Specification $other): AndSpecification
    {
        return new AndSpecification($this, $other);
    }

    /**
     * Requires either this rule or the supplied rule to pass.
     *
     * @return OrSpecification<TCandidate>
     */
    final public function or(Specification $other): OrSpecification
    {
        return new OrSpecification($this, $other);
    }

    /**
     * Reverses this rule, so a passing candidate becomes a failing one.
     *
     * @return NotSpecification<TCandidate>
     */
    final public function not(): NotSpecification
    {
        return new NotSpecification($this);
    }
}
