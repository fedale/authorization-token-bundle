<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Result;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Outcome of issuing a token.
 *
 * The plain token is present here and ONLY here — it is never persisted and
 * cannot be recovered later. The application must transmit it immediately
 * (link, e-mail, …) and then discard it.
 */
final class IssueResult
{
    public function __construct(
        public readonly string $plainToken,
        public readonly AuthorizationToken $token,
    ) {
    }
}
