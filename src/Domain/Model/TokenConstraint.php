<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Model;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;

/**
 * A named, parameterised rule attached to a token at issue time.
 *
 * The bundle does not interpret the parameters; the matching
 * TokenConstraintValidator (selected by name) reads them at validation time.
 * Example: new TokenConstraint('ip', ['ip' => '203.0.113.7']).
 */
final class TokenConstraint
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly array $parameters = [],
    ) {
        if ($name === '') {
            throw new AuthorizationTokenException('A constraint name cannot be empty.');
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }
}
