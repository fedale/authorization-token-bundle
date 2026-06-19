<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Command;

use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;

/**
 * Immutable instruction to revoke a token by its identifier.
 */
final class RevokeTokenCommand
{
    public function __construct(
        public readonly TokenId $tokenId,
    ) {
    }
}
