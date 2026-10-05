<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\PasswordHash;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;

/** Stores Identity aggregates beside Laravel's authentication credentials. */
final readonly class DatabaseUserRepository implements UserRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(User $user): void
    {
        $existing = $this->connection->table('users')
            ->where('identity_user_id', $user->id()->value())
            ->first();

        $values = [
            'identity_user_id' => $user->id()->value(),
            'email' => $user->email()->value(),
            'password' => $user->passwordHash()->value(),
            'identity_version' => $user->version(),
            'email_verified_at' => $user->emailVerifiedAt()?->setTimezone(new DateTimeZone('UTC')),
            'updated_at' => now(),
        ];

        if ($existing === null) {
            // The legacy name column remains required until customer profiles own all display names.
            $this->connection->table('users')
                ->insert([
                    ...$values,
                    'name' => $user->email()->value(),
                    'created_at' => $user->registeredAt()->setTimezone(new DateTimeZone('UTC')),
                ]);

            return;
        }

        $this->connection->table('users')
            ->where('id', $existing->id)
            ->update($values);
    }

    public function findById(UserId $id): ?User
    {
        return $this->hydrate($this->connection->table('users')
            ->where('identity_user_id', $id->value())
            ->first());
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        return $this->hydrate($this->connection->table('users')
            ->where('email', $email->value())
            ->first());
    }

    public function emailExists(EmailAddress $email): bool
    {
        return $this->connection->table('users')
            ->where('email', $email->value())
            ->exists();
    }

    private function hydrate(?object $record): ?User
    {
        // Old Laravel-only users are not valid domain identities until linked to a UUID.
        if ($record === null || $record->identity_user_id === null) {
            return null;
        }

        return User::reconstitute(
            new UserId($record->identity_user_id),
            new EmailAddress($record->email),
            new PasswordHash($record->password),
            new DateTimeImmutable($record->created_at),
            (int) $record->identity_version,
            $record->email_verified_at === null ? null : new DateTimeImmutable($record->email_verified_at),
        );
    }
}
