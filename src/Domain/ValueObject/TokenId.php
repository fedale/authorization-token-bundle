<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\ValueObject;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;

/**
 * Opaque, storage-friendly identifier of a token aggregate.
 *
 * Generated as 32 hex characters (128 bits) without depending on symfony/uid,
 * keeping the core bundle dependency-free.
 */
final class TokenId
{
    private function __construct(
        public readonly string $value,
    ) {
        if ($value === '') {
            throw new AuthorizationTokenException('TokenId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
