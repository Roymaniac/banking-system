<?php

declare(strict_types=1);

namespace Identity\Domain\User\ValueObject;

use Shared\Domain\Identifier\Uuid;

/**
 * A UserId is a UUID whose type makes it impossible to confuse a user with
 * another kind of entity, such as a customer or account.
 */
final class UserId extends Uuid {}
