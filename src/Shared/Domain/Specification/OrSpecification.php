<?php

declare(strict_types=1);

namespace Shared\Domain\Specification;

/**
 * A combined rule that passes when at least one child rule passes.
 */
final readonly class OrSpecification implements Specification
{
    /**
     * @param  Specification<TCandidate>  $left
     * @param  Specification<TCandidate>  $right
     */
    public function __construct(
        private Specification $left,
        private Specification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate)
            || $this->right->isSatisfiedBy($candidate);
    }
}
