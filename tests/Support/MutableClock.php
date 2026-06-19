<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Tests\Support;

use Psr\Clock\ClockInterface;

/**
 * Deterministic clock for tests; time only moves when the test advances it.
 */
final class MutableClock implements ClockInterface
{
    public function __construct(
        private \DateTimeImmutable $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ) {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
