<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Constraint;

/**
 * Runtime information about the attempt to validate or consume a token.
 *
 * Built by the application (typically from the current HTTP request) and
 * handed to TokenManager::validate()/consume(). Constraint validators read
 * from it to compare the token's recorded constraints against the live
 * request. Extra, use-case-specific data goes into $attributes.
 */
final class ValidationContext
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly array $attributes = [],
    ) {
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public static function empty(): self
    {
        return new self();
    }
}
