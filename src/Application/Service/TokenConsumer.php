<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Service;

use Fedale\AuthorizationTokenBundle\Application\Command\ConsumeTokenCommand;
use Fedale\AuthorizationTokenBundle\Application\Result\ConsumeResult;
use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenWriteRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Domain\Event\TokenConsumed;
use Fedale\AuthorizationTokenBundle\Infrastructure\EventDispatcher\DomainEventDispatcher;
use Psr\Clock\ClockInterface;

/**
 * Validates a token and then records a usage, persisting the new state.
 *
 * Reuses TokenValidator::assert() so consumption and validation share exactly
 * the same checks — a token can never be consumed unless it would also
 * validate.
 */
final class TokenConsumer
{
    public function __construct(
        private readonly TokenValidator $validator,
        private readonly AuthorizationTokenWriteRepositoryInterface $repository,
        private readonly ClockInterface $clock,
        private readonly DomainEventDispatcher $events,
    ) {
    }

    public function consume(ConsumeTokenCommand $command): ConsumeResult
    {
        $token = $this->validator->assert($command->plainToken, $command->action, $command->context);

        $token->recordUsage($this->clock->now());

        $this->repository->save($token);
        $this->events->dispatch(new TokenConsumed($token));

        return new ConsumeResult($token, $token->isConsumed());
    }
}
