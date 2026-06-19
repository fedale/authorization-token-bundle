<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Constraint\Validator;

use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Contract\TokenConstraintValidatorInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\ConstraintViolationException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Binds a token to the IP address it was issued for.
 *
 * Issue with: new TokenConstraint('ip', ['ip' => $request->getClientIp()]).
 */
final class IpConstraintValidator implements TokenConstraintValidatorInterface
{
    public const NAME = 'ip';

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

        $expected = $constraint->get('ip');

        if (null === $expected) {
            return;
        }

        if ($context->ip !== $expected) {
            throw ConstraintViolationException::forConstraint(self::NAME, 'request IP does not match the issuing IP.');
        }
    }
}
