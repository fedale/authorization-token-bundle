<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Contract;

use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;

/**
 * Derives the persistable hash from a plain-text token.
 *
 * Hashing must be deterministic so that a presented token can be located by
 * its hash. The plain token is never stored; only the value produced here is.
 */
interface TokenHasherInterface
{
    public function hash(string $plainToken): TokenHash;

    /**
     * Constant-time comparison of a plain token against a stored hash.
     */
    public function verify(string $plainToken, TokenHash $hash): bool;
}
