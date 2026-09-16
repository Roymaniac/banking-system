<?php

declare(strict_types=1);

use Shared\Domain\Specification\AbstractSpecification;

final readonly class SpecificationTestApplicant
{
    public function __construct(
        public int $age,
        public bool $hasValidIdentification,
    ) {}
}

/** @extends AbstractSpecification<SpecificationTestApplicant> */
final class AdultApplicantSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate instanceof SpecificationTestApplicant
            && $candidate->age >= 18;
    }
}

/** @extends AbstractSpecification<SpecificationTestApplicant> */
final class HasValidIdentificationSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate instanceof SpecificationTestApplicant
            && $candidate->hasValidIdentification;
    }
}

it('combines business rules with and', function (): void {
    $eligibleApplicant = new SpecificationTestApplicant(21, true);
    $underageApplicant = new SpecificationTestApplicant(17, true);

    $specification = (new AdultApplicantSpecification)
        ->and(new HasValidIdentificationSpecification);

    expect($specification->isSatisfiedBy($eligibleApplicant))->toBeTrue()
        ->and($specification->isSatisfiedBy($underageApplicant))->toBeFalse();
});

it('combines business rules with or', function (): void {
    $adultWithoutIdentification = new SpecificationTestApplicant(21, false);
    $underageWithIdentification = new SpecificationTestApplicant(17, true);
    $ineligibleApplicant = new SpecificationTestApplicant(17, false);

    $specification = (new AdultApplicantSpecification)
        ->or(new HasValidIdentificationSpecification);

    expect($specification->isSatisfiedBy($adultWithoutIdentification))->toBeTrue()
        ->and($specification->isSatisfiedBy($underageWithIdentification))->toBeTrue()
        ->and($specification->isSatisfiedBy($ineligibleApplicant))->toBeFalse();
});

it('negates a business rule', function (): void {
    $specification = (new AdultApplicantSpecification)->not();

    expect($specification->isSatisfiedBy(new SpecificationTestApplicant(17, true)))->toBeTrue()
        ->and($specification->isSatisfiedBy(new SpecificationTestApplicant(21, true)))->toBeFalse();
});
