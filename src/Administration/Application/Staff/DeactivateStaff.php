<?php

declare(strict_types=1);

namespace Administration\Application\Staff;

use Administration\Domain\Staff\Exception\StaffNotFound;
use Administration\Domain\Staff\Repository\StaffRepository;
use Administration\Domain\Staff\ValueObject\StaffId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\UuidGenerator;

/** Deactivates a staff record while preserving its employment history. */
final readonly class DeactivateStaff
{
    public function __construct(
        private StaffRepository $staff,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(StaffId $staffId, ?CorrelationId $correlationId = null): void
    {
        $events = $this->transactions->run(function () use ($staffId, $correlationId): array {
            $staff = $this->staff->findByIdForUpdate($staffId);
            if ($staff === null) {
                throw StaffNotFound::create();
            }
            $staff->deactivate($this->clock->now(), $this->ids->generate(), $correlationId);
            $this->staff->save($staff);

            return $staff->pullDomainEvents();
        });
        $this->events->publish($events);
    }
}
