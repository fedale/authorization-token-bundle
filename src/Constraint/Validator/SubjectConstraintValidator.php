<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Constraint\Validator;

use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Contract\TokenConstraintValidatorInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\ConstraintViolationException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;

/**
 * Requires that the subject acting on the token matches the subject it was
 * issued for.
 *
 * Issue with: new TokenConstraint('subject') (the expected subject is the
 * token's own subject). At validation time, pass the acting subject in the
 * context attributes under the "subject" key, as a SubjectReference or its
 * "type#id" string form.
 */
final class SubjectConstraintValidator implements TokenConstraintValidatorInterface
{
    public const NAME = 'subject';

    public function supports(string $constraint): bool
    {
        return self::NAME === $constraint;
    }

    public function validate(AuthorizationToken $token, ValidationContext $context): void
    {
        if (null === $token->getConstraint(self::NAME)) {
            return;
        }

        $expected = $token->getSubject();

        if (null === $expected) {
            throw ConstraintViolationException::forConstraint(self::NAME, 'token has no bound subject to match against.');
        }

        $actual = $this->normalize($context->attribute(self::NAME));

        if (null === $actual || !$expected->equals($actual)) {
            throw ConstraintViolationException::forConstraint(self::NAME, 'acting subject does not match the bound subject.');
        }
    }

    private function normalize(mixed $value): ?SubjectReference
    {
        if ($value instanceof SubjectReference) {
            return $value;
        }

        if (\is_string($value) && '' !== $value) {
            return SubjectReference::fromString($value);
        }

        return null;
    }
}
