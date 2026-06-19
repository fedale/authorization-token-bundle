<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Exception;

/**
 * A token constraint (IP, user agent, subject, …) was not satisfied.
 */
final class ConstraintViolationException extends AuthorizationTokenException
{
    public static function forConstraint(string $constraint, string $reason): self
    {
        return new self(sprintf('Constraint "%s" violated: %s', $constraint, $reason));
    }
}
