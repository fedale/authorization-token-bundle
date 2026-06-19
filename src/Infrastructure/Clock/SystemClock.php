<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Infrastructure\Clock;

use Psr\Clock\ClockInterface;

/**
 * Default clock backed by the system time.
 *
 * The bundle depends on the PSR-20 ClockInterface rather than reading "now"
 * implicitly, so tests can substitute a frozen clock. In a Symfony 6.4+
 * application this can be swapped for symfony/clock's clock service.
 */
final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
