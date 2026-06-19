<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Constraint\Registry;

use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Contract\TokenConstraintValidatorInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\ConstraintViolationException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Runs every constraint attached to a token through the validators that
 * support it.
 *
 * Validators are collected via the "authorization_token.constraint_validator"
 * tag by RegisterConstraintValidatorsPass. The first violating constraint
 * stops the process by throwing.
 */
final class ConstraintValidatorRegistry
{
    /** @var list<TokenConstraintValidatorInterface> */
    private readonly array $validators;

    /**
     * @param iterable<TokenConstraintValidatorInterface> $validators
     */
    public function __construct(iterable $validators = [])
    {
        $this->validators = $validators instanceof \Traversable
            ? iterator_to_array($validators, false)
            : array_values($validators);
    }

    /**
     * @throws ConstraintViolationException on the first unsatisfied constraint
     */
    public function validate(AuthorizationToken $token, ValidationContext $context): void
    {
        foreach ($token->getConstraints() as $constraint) {
            foreach ($this->validators as $validator) {
                if ($validator->supports($constraint->name)) {
                    $validator->validate($token, $context);
                }
            }
        }
    }
}
