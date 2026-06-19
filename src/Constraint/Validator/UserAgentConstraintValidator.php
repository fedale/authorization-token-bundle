<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Constraint\Validator;

use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Contract\TokenConstraintValidatorInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\ConstraintViolationException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Binds a token to the User-Agent it was issued for.
 *
 * Issue with: new TokenConstraint('user_agent', ['user_agent' => $ua]).
 */
final class UserAgentConstraintValidator implements TokenConstraintValidatorInterface
{
    public const NAME = 'user_agent';

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

        $expected = $constraint->get('user_agent');

        if (null === $expected) {
            return;
        }

        if ($context->userAgent !== $expected) {
            throw ConstraintViolationException::forConstraint(self::NAME, 'request User-Agent does not match the issuing User-Agent.');
        }
    }
}
