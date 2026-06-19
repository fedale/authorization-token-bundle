<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Service;

use Fedale\AuthorizationTokenBundle\Application\Command\RevokeTokenCommand;
use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenReadRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenWriteRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Domain\Event\TokenRevoked;
use Fedale\AuthorizationTokenBundle\Domain\Exception\TokenNotFoundException;
use Fedale\AuthorizationTokenBundle\Infrastructure\EventDispatcher\DomainEventDispatcher;
use Psr\Clock\ClockInterface;

/**
 * Marks a token as revoked, so it can no longer validate or be consumed.
 *
 * Revoking an already-revoked token is a no-op at the domain level; the event
 * still reflects the current state.
 */
final class TokenRevoker
{
    public function __construct(
        private readonly AuthorizationTokenReadRepositoryInterface $readRepository,
        private readonly AuthorizationTokenWriteRepositoryInterface $writeRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventDispatcher $events,
    ) {
    }

    public function revoke(RevokeTokenCommand $command): void
    {
        $token = $this->readRepository->findById($command->tokenId);

        if (null === $token) {
            throw TokenNotFoundException::forPresentedToken();
        }

        $token->revoke($this->clock->now());

        $this->writeRepository->save($token);
        $this->events->dispatch(new TokenRevoked($token));
    }
}
