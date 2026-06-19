<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Event;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Dispatched after a token usage has been recorded.
 *
 * Inspect $token->isConsumed() to tell a single-use token that has just been
 * exhausted from a multi-use token that still has remaining uses.
 */
final class TokenConsumed
{
    public function __construct(
        public readonly AuthorizationToken $token,
    ) {
    }
}
