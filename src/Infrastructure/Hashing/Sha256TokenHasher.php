<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Infrastructure\Hashing;

use Fedale\AuthorizationTokenBundle\Contract\TokenHasherInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;

/**
 * Deterministic hashing using a fast, fixed-length algorithm (sha256 by
 * default).
 *
 * A fast hash is appropriate here — unlike passwords, these tokens are
 * high-entropy random values, so they are not vulnerable to brute force and
 * do not need a slow KDF. Determinism is required so a presented token can be
 * located by its hash.
 */
final class Sha256TokenHasher implements TokenHasherInterface
{
    public function __construct(
        private readonly string $algorithm = 'sha256',
    ) {
        if (!\in_array($algorithm, hash_algos(), true)) {
            throw new AuthorizationTokenException(sprintf('Unsupported hashing algorithm "%s".', $algorithm));
        }
    }

    public function hash(string $plainToken): TokenHash
    {
        return new TokenHash(hash($this->algorithm, $plainToken));
    }

    public function verify(string $plainToken, TokenHash $hash): bool
    {
        return hash_equals($hash->value, hash($this->algorithm, $plainToken));
    }
}
