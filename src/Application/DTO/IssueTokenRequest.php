<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\DTO;

use Fedale\AuthorizationTokenBundle\Application\Command\IssueTokenCommand;
use Fedale\AuthorizationTokenBundle\Domain\Model\TokenConstraint;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;

/**
 * Public, ergonomic input for issuing a token — convenient to build in a
 * controller or service. TokenManager::issueRequest() maps it to the internal
 * command.
 */
final class IssueTokenRequest
{
    /**
     * @param list<TokenConstraint> $constraints
     * @param array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $action,
        public readonly ?SubjectReference $subject = null,
        public readonly ?int $ttlSeconds = null,
        public readonly ?int $maxUsages = null,
        public readonly array $constraints = [],
        public readonly array $metadata = [],
    ) {
    }

    public function toCommand(): IssueTokenCommand
    {
        return new IssueTokenCommand(
            $this->action,
            $this->subject,
            $this->ttlSeconds,
            $this->maxUsages,
            $this->constraints,
            $this->metadata,
        );
    }
}
