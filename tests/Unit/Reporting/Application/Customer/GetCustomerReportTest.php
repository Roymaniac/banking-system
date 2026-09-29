<?php

declare(strict_types=1);

use Customer\Domain\Customer\ValueObject\CustomerId;
use Reporting\Application\Customer\CustomerReportQuery;
use Reporting\Application\Customer\Exception\CustomerReportNotFound;
use Reporting\Application\Customer\GetCustomerReport;
use Reporting\Application\Customer\View\CustomerReportView;

it('returns the customer view supplied by the reporting query', function (): void {
    $customerId = CustomerId::generate();
    $view = new CustomerReportView(
        $customerId->value(),
        'user-id',
        'Ada',
        null,
        'Okafor',
        new DateTimeImmutable('1990-01-02'),
        new DateTimeImmutable('2026-09-27T09:00:00+01:00'),
        [],
        [],
        [],
    );
    $query = Mockery::mock(CustomerReportQuery::class);
    $query->shouldReceive('find')->once()->with($customerId)->andReturn($view);

    $report = (new GetCustomerReport($query))->handle($customerId);

    expect($report)->toBe($view)
        ->and($report->fullName())->toBe('Ada Okafor')
        ->and($report->accountCount())->toBe(0);
});

it('reports clearly when the requested customer does not exist', function (): void {
    $customerId = CustomerId::generate();
    $query = Mockery::mock(CustomerReportQuery::class);
    $query->shouldReceive('find')->once()->with($customerId)->andReturnNull();

    (new GetCustomerReport($query))->handle($customerId);
})->throws(CustomerReportNotFound::class);
