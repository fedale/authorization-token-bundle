<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Event;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Dispatched when an expired token is presented for validation or consumption.
 *
 * Useful for housekeeping (e.g. scheduling removal) or auditing attempts to
 * use stale tokens.
 */
final class TokenExpired
{
    public function __construct(
        public readonly AuthorizationToken $token,
    ) {
    }
}
