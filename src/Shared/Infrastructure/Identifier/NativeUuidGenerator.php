<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Identifier;

use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final class NativeUuidGenerator implements UuidGenerator
{
    /**
     * Generates a new UUID.
     *
     * @return Uuid
     */
    public function generate(): Uuid
    {
        return Uuid::generate();
    }
}
