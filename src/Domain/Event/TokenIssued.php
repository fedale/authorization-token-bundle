<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Event;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Dispatched after a new token has been generated and persisted.
 */
final class TokenIssued
{
    public function __construct(
        public readonly AuthorizationToken $token,
    ) {
    }
}
