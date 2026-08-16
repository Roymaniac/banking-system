<?php

declare(strict_types=1);

namespace Shared\Domain\Specification;

/**
 * A combined rule that only passes when both child rules pass.
 */
final readonly class AndSpecification implements Specification
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
            && $this->right->isSatisfiedBy($candidate);
    }
}
