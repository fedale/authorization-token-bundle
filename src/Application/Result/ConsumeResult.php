<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Result;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Outcome of consuming a token.
 *
 * $fullyConsumed is true when this usage exhausted the token's allowance
 * (the common single-use case). For multi-use tokens it stays false until the
 * last permitted use.
 */
final class ConsumeResult
{
    public function __construct(
        public readonly AuthorizationToken $token,
        public readonly bool $fullyConsumed,
    ) {
    }

    public function remainingUses(): int
    {
        return $this->token->getUsage()->remaining();
    }
}
