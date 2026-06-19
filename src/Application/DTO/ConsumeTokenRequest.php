<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\DTO;

use Fedale\AuthorizationTokenBundle\Application\Command\ConsumeTokenCommand;
use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;

/**
 * Public, ergonomic input for consuming a token. TokenManager::consumeRequest()
 * maps it to the internal command.
 */
final class ConsumeTokenRequest
{
    public function __construct(
        public readonly string $plainToken,
        public readonly string $action,
        public readonly ?ValidationContext $context = null,
    ) {
    }

    public function toCommand(): ConsumeTokenCommand
    {
        return new ConsumeTokenCommand(
            $this->plainToken,
            $this->action,
            $this->context ?? ValidationContext::empty(),
        );
    }
}
