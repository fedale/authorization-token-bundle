<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Contract;

use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Domain\Exception\ConstraintViolationException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Extension point for additional, application-defined validation logic.
 *
 * Services implementing this interface are auto-tagged with
 * "authorization_token.constraint_validator" and collected into the
 * ConstraintValidatorRegistry by a compiler pass.
 */
interface TokenConstraintValidatorInterface
{
    /**
     * Whether this validator handles the given constraint name
     * (e.g. "ip", "user_agent", "subject", "max_usage").
     */
    public function supports(string $constraint): bool;

    /**
     * Validate the token against the runtime context.
     *
     * @throws ConstraintViolationException when the constraint is not satisfied
     */
    public function validate(AuthorizationToken $token, ValidationContext $context): void;
}
