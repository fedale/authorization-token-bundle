<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Command;

use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;

/**
 * Immutable instruction to consume a presented token (validate, then record a
 * usage).
 */
final class ConsumeTokenCommand
{
    public function __construct(
        public readonly string $plainToken,
        public readonly string $action,
        public readonly ValidationContext $context,
    ) {
    }
}
