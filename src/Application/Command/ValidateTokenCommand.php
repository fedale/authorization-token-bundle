<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Command;

use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;

/**
 * Immutable instruction to validate a presented token. No state change.
 */
final class ValidateTokenCommand
{
    public function __construct(
        public readonly string $plainToken,
        public readonly string $action,
        public readonly ValidationContext $context,
    ) {
    }
}
