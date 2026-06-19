<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\ValueObject;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;

/**
 * The persisted, deterministic fingerprint of a plain token.
 *
 * This is the only token-derived value that ever reaches storage.
 */
final class TokenHash
{
    public function __construct(
        public readonly string $value,
    ) {
        if ($value === '') {
            throw new AuthorizationTokenException('TokenHash cannot be empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
