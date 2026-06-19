<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Event;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Dispatched after a token has been revoked.
 */
final class TokenRevoked
{
    public function __construct(
        public readonly AuthorizationToken $token,
    ) {
    }
}
