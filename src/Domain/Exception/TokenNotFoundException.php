<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Exception;

/**
 * No token matches the presented value (or it matches a different action).
 *
 * Action mismatches are reported as "not found" on purpose, to avoid leaking
 * the existence of a token issued for a different action.
 */
final class TokenNotFoundException extends AuthorizationTokenException
{
    public static function forPresentedToken(): self
    {
        return new self('No authorization token matches the presented value.');
    }
}
