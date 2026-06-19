<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Service;

use Fedale\AuthorizationTokenBundle\Application\Command\ValidateTokenCommand;
use Fedale\AuthorizationTokenBundle\Application\Result\ValidationResult;
use Fedale\AuthorizationTokenBundle\Constraint\Registry\ConstraintValidatorRegistry;
use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenReadRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Contract\TokenHasherInterface;
use Fedale\AuthorizationTokenBundle\Domain\Event\TokenExpired;
use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;
use Fedale\AuthorizationTokenBundle\Domain\Exception\TokenConsumedException;
use Fedale\AuthorizationTokenBundle\Domain\Exception\TokenExpiredException;
use Fedale\AuthorizationTokenBundle\Domain\Exception\TokenNotFoundException;
use Fedale\AuthorizationTokenBundle\Domain\Exception\TokenRevokedException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Infrastructure\EventDispatcher\DomainEventDispatcher;
use Psr\Clock\ClockInterface;

/**
 * Validates a presented token without changing its state.
 *
 * The throwing assert() carries the full set of checks and is reused by the
 * consumer; validate() is the non-throwing public wrapper that returns a
 * ValidationResult.
 */
final class TokenValidator
{
    public function __construct(
        private readonly TokenHasherInterface $hasher,
        private readonly AuthorizationTokenReadRepositoryInterface $repository,
        private readonly ClockInterface $clock,
        private readonly ConstraintValidatorRegistry $constraints,
        private readonly DomainEventDispatcher $events,
    ) {
    }

    public function validate(ValidateTokenCommand $command): ValidationResult
    {
        try {
            $token = $this->assert($command->plainToken, $command->action, $command->context);

            return ValidationResult::valid($token);
        } catch (AuthorizationTokenException $exception) {
            return ValidationResult::invalid($exception);
        }
    }

    /**
     * Run every check and return the matching token, or throw the first failure.
     *
     * @throws TokenNotFoundException
     * @throws TokenRevokedException
     * @throws TokenConsumedException
     * @throws TokenExpiredException
     * @throws \Fedale\AuthorizationTokenBundle\Domain\Exception\ConstraintViolationException
     */
    public function assert(string $plainToken, string $action, ValidationContext $context): AuthorizationToken
    {
        $token = $this->repository->findByHash($this->hasher->hash($plainToken));

        // An unknown token and an action mismatch are reported identically so
        // the existence of a token for another action is never leaked.
        if (null === $token || $token->getAction() !== $action) {
            throw TokenNotFoundException::forPresentedToken();
        }

        if ($token->isRevoked()) {
            throw TokenRevokedException::create();
        }

        if ($token->isConsumed()) {
            throw TokenConsumedException::create();
        }

        if ($token->isExpired($this->clock->now())) {
            $this->events->dispatch(new TokenExpired($token));

            throw TokenExpiredException::create();
        }

        $this->constraints->validate($token, $context);

        return $token;
    }
}
