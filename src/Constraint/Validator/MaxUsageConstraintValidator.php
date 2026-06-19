<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Constraint\Validator;

use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Contract\TokenConstraintValidatorInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\ConstraintViolationException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Enforces a per-constraint usage ceiling, independent of the token's own
 * maxUsages lifecycle.
 *
 * Issue with: new TokenConstraint('max_usage', ['max' => 3]). This lets an
 * application cap validation/consumption attempts more tightly than the
 * structural maxUsages without changing the token's consumed state machine.
 */
final class MaxUsageConstraintValidator implements TokenConstraintValidatorInterface
{
    public const NAME = 'max_usage';

    public function supports(string $constraint): bool
    {
        return self::NAME === $constraint;
    }

    public function validate(AuthorizationToken $token, ValidationContext $context): void
    {
        $constraint = $token->getConstraint(self::NAME);

        if (null === $constraint) {
            return;
        }

        $max = $constraint->get('max');

        if (!\is_int($max)) {
            return;
        }

        if ($token->getUsageCount() >= $max) {
            throw ConstraintViolationException::forConstraint(self::NAME, sprintf('usage limit of %d reached.', $max));
        }
    }
}
