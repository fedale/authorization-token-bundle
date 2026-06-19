<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Service;

use Fedale\AuthorizationTokenBundle\Application\Command\IssueTokenCommand;
use Fedale\AuthorizationTokenBundle\Application\Command\RevokeTokenCommand;
use Fedale\AuthorizationTokenBundle\Application\Command\ValidateTokenCommand;
use Fedale\AuthorizationTokenBundle\Application\DTO\ConsumeTokenRequest;
use Fedale\AuthorizationTokenBundle\Application\DTO\IssueTokenRequest;
use Fedale\AuthorizationTokenBundle\Application\Result\ConsumeResult;
use Fedale\AuthorizationTokenBundle\Application\Result\IssueResult;
use Fedale\AuthorizationTokenBundle\Application\Result\ValidationResult;
use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Contract\TokenManagerInterface;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;

/**
 * Public facade delegating to the focused single-responsibility services.
 *
 * Applications type-hint Contract\TokenManagerInterface and never touch the
 * issuer/validator/consumer/revoker directly.
 */
final class TokenManager implements TokenManagerInterface
{
    public function __construct(
        private readonly TokenIssuer $issuer,
        private readonly TokenValidator $validator,
        private readonly TokenConsumer $consumer,
        private readonly TokenRevoker $revoker,
    ) {
    }

    public function issue(
        string $action,
        ?SubjectReference $subject = null,
        ?int $ttlSeconds = null,
        ?int $maxUsages = null,
        array $constraints = [],
        array $metadata = [],
    ): IssueResult {
        return $this->issuer->issue(
            new IssueTokenCommand($action, $subject, $ttlSeconds, $maxUsages, $constraints, $metadata),
        );
    }

    public function issueRequest(IssueTokenRequest $request): IssueResult
    {
        return $this->issuer->issue($request->toCommand());
    }

    public function validate(
        string $token,
        string $action,
        ?ValidationContext $context = null,
    ): ValidationResult {
        return $this->validator->validate(
            new ValidateTokenCommand($token, $action, $context ?? ValidationContext::empty()),
        );
    }

    public function consume(
        string $token,
        string $action,
        ?ValidationContext $context = null,
    ): ConsumeResult {
        return $this->consumeRequest(new ConsumeTokenRequest($token, $action, $context));
    }

    public function consumeRequest(ConsumeTokenRequest $request): ConsumeResult
    {
        return $this->consumer->consume($request->toCommand());
    }

    public function revoke(TokenId $tokenId): void
    {
        $this->revoker->revoke(new RevokeTokenCommand($tokenId));
    }
}
