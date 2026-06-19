<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Exception;

final class TokenExpiredException extends AuthorizationTokenException
{
    public static function create(): self
    {
        return new self('The authorization token has expired.');
    }
}
