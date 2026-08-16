<?php

declare(strict_types=1);

namespace Shared\Domain\Specification;

/**
 * A combined rule that reverses the result of another rule.
 */
final readonly class NotSpecification implements Specification
{
    /**
     * @param  Specification<TCandidate>  $specification
     */
    public function __construct(
        private Specification $specification,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return ! $this->specification->isSatisfiedBy($candidate);
    }
}
