<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\ValueObject;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;

/**
 * The instant at which a token stops being valid.
 *
 * Encapsulates the "is it expired?" decision so it is expressed once and
 * tested against an injected clock rather than against "now" read implicitly.
 */
final class Expiration
{
    public function __construct(
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * Build an expiration that is $ttlSeconds after $issuedAt.
     */
    public static function fromTtl(\DateTimeImmutable $issuedAt, int $ttlSeconds): self
    {
        if ($ttlSeconds <= 0) {
            throw new AuthorizationTokenException(sprintf('TTL must be a positive number of seconds, %d given.', $ttlSeconds));
        }

        return new self($issuedAt->modify(sprintf('+%d seconds', $ttlSeconds)));
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }
}
