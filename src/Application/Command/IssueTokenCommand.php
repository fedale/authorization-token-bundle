<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Command;

use Fedale\AuthorizationTokenBundle\Domain\Model\TokenConstraint;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;

/**
 * Immutable instruction to issue a token.
 *
 * A null $ttlSeconds or $maxUsages means "use the configured default for this
 * action"; the TokenIssuer resolves them.
 *
 * @phpstan-type Metadata array<string, mixed>
 */
final class IssueTokenCommand
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
}
