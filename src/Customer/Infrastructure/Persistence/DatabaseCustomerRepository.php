<?php

declare(strict_types=1);

namespace Customer\Infrastructure\Persistence;

use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;
use Shared\Domain\Exception\ConcurrencyException;

/** Stores customer profiles with Laravel's database connection. */
final readonly class DatabaseCustomerRepository implements CustomerRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Customer $customer): void
    {
        $storedVersion = $this->connection->table('customers')
            ->where('id', $customer->id()->value())
            ->value('version');

        $values = [
            'user_id' => $customer->userId()->value(),
            'first_name' => $customer->name()->firstName(),
            'middle_name' => $customer->name()->middleName(),
            'last_name' => $customer->name()->lastName(),
            'date_of_birth' => $customer->dateOfBirth()->value(),
            'registered_at' => $customer->registeredAt(),
            'version' => $customer->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('customers')->insert([
                'id' => $customer->id()->value(),
                ...$values,
            ]);

            return;
        }

        $expectedVersion = $customer->version() - 1;
        $updated = $this->connection->table('customers')
            ->where('id', $customer->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate(
                $customer->id(),
                $expectedVersion,
                (int) $storedVersion,
            );
        }
    }

    public function findById(CustomerId $id): ?Customer
    {
        return $this->hydrate($this->connection->table('customers')->where('id', $id->value())->first());
    }

    public function findByUserId(UserId $userId): ?Customer
    {
        return $this->hydrate($this->connection->table('customers')->where('user_id', $userId->value())->first());
    }

    public function existsForUser(UserId $userId): bool
    {
        return $this->connection->table('customers')->where('user_id', $userId->value())->exists();
    }

    private function hydrate(?object $record): ?Customer
    {
        if ($record === null) {
            return null;
        }

        // The stored date was valid when written; using its registration date
        // as the reference still protects reconstitution from impossible data.
        $registeredAt = new DateTimeImmutable($record->registered_at);

        return Customer::reconstitute(
            id: new CustomerId($record->id),
            userId: new UserId($record->user_id),
            name: new PersonalName($record->first_name, $record->middle_name, $record->last_name),
            dateOfBirth: DateOfBirth::fromString($record->date_of_birth, $registeredAt),
            registeredAt: $registeredAt,
            version: (int) $record->version,
        );
    }
}
