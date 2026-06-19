<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Model;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;

/**
 * Usage accounting for a token: how many times it has been used out of its
 * allowance.
 *
 * Immutable value object. AuthorizationToken keeps the raw counters and
 * exposes a TokenUsage snapshot via getUsage() so callers can reason about
 * remaining uses without touching primitive fields.
 */
final class TokenUsage
{
    public function __construct(
        public readonly int $count,
        public readonly int $max,
    ) {
        if ($count < 0) {
            throw new AuthorizationTokenException('Usage count cannot be negative.');
        }

        if ($max < 1) {
            throw new AuthorizationTokenException('Max usages must be at least 1.');
        }
    }

    public function isExhausted(): bool
    {
        return $this->count >= $this->max;
    }

    public function remaining(): int
    {
        return max(0, $this->max - $this->count);
    }
}
