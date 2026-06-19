<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Service;

use Fedale\AuthorizationTokenBundle\Application\Command\IssueTokenCommand;
use Fedale\AuthorizationTokenBundle\Application\Result\IssueResult;
use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenWriteRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Contract\TokenGeneratorInterface;
use Fedale\AuthorizationTokenBundle\Contract\TokenHasherInterface;
use Fedale\AuthorizationTokenBundle\Domain\Event\TokenIssued;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\Expiration;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;
use Fedale\AuthorizationTokenBundle\Infrastructure\EventDispatcher\DomainEventDispatcher;
use Psr\Clock\ClockInterface;

/**
 * Generates, hashes and persists a new token, returning the plain value once.
 *
 * TTL and max-usages are resolved here: an explicit value on the command wins,
 * otherwise the per-action configuration, otherwise the global defaults.
 */
final class TokenIssuer
{
    /**
     * @param array<string, array{ttl?: int, max_usages?: int}> $actions
     */
    public function __construct(
        private readonly TokenGeneratorInterface $generator,
        private readonly TokenHasherInterface $hasher,
        private readonly AuthorizationTokenWriteRepositoryInterface $repository,
        private readonly ClockInterface $clock,
        private readonly DomainEventDispatcher $events,
        private readonly int $generatorLength,
        private readonly int $defaultTtlSeconds,
        private readonly int $defaultMaxUsages,
        private readonly array $actions = [],
    ) {
    }

    public function issue(IssueTokenCommand $command): IssueResult
    {
        $now = $this->clock->now();
        $action = $command->action;

        $ttl = $command->ttlSeconds ?? $this->actions[$action]['ttl'] ?? $this->defaultTtlSeconds;
        $maxUsages = $command->maxUsages ?? $this->actions[$action]['max_usages'] ?? $this->defaultMaxUsages;

        $plainToken = $this->generator->generate($this->generatorLength);

        $token = AuthorizationToken::issue(
            TokenId::generate(),
            $this->hasher->hash($plainToken),
            $action,
            $command->subject,
            $now,
            Expiration::fromTtl($now, $ttl)->expiresAt,
            $maxUsages,
            $command->constraints,
            $command->metadata,
        );

        $this->repository->save($token);
        $this->events->dispatch(new TokenIssued($token));

        return new IssueResult($plainToken, $token);
    }
}
