<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Contract;

use Fedale\AuthorizationTokenBundle\Application\Result\ConsumeResult;
use Fedale\AuthorizationTokenBundle\Application\Result\IssueResult;
use Fedale\AuthorizationTokenBundle\Application\Result\ValidationResult;
use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Domain\Model\TokenConstraint;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;

/**
 * The single entry point applications depend on.
 *
 * This is the bundle's public facade over the focused issuer/validator/
 * consumer/revoker services.
 */
interface TokenManagerInterface
{
    /**
     * @param list<TokenConstraint> $constraints
     * @param array<string, mixed>  $metadata
     */
    public function issue(
        string $action,
        ?SubjectReference $subject = null,
        ?int $ttlSeconds = null,
        ?int $maxUsages = null,
        array $constraints = [],
        array $metadata = [],
    ): IssueResult;

    /**
     * Validate a presented token without changing its state.
     */
    public function validate(
        string $token,
        string $action,
        ?ValidationContext $context = null,
    ): ValidationResult;

    /**
     * Validate then record a usage of the presented token.
     */
    public function consume(
        string $token,
        string $action,
        ?ValidationContext $context = null,
    ): ConsumeResult;

    public function revoke(TokenId $tokenId): void;
}
