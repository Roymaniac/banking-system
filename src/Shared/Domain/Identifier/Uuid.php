<?php

declare(strict_types=1);

namespace Shared\Domain\Identifier;

use InvalidArgumentException;

class Uuid extends Identifier
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtolower($value);

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
            throw new InvalidArgumentException('The value must be a valid RFC 4122 UUID.');
        }

        $this->value = $value;
    }

    /**
     * Generates a new UUID and returns an instance of Uuid.
     *
     * @return static
     */
    public static function generate(): static
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return new static(sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        ));
    }

    /**
     * Returns the string representation of this UUID.
     *
     * @return string
     */
    final public function value(): string
    {
        return $this->value;
    }
}
