<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Contract;

/**
 * Produces the opaque plain-text token handed back to the caller exactly once.
 *
 * The generated value is what the application transmits to the end user
 * (e.g. inside a link or an e-mail). Only its hash is ever persisted.
 */
interface TokenGeneratorInterface
{
    /**
     * @param int $length Number of characters of the resulting token.
     */
    public function generate(int $length): string;
}
