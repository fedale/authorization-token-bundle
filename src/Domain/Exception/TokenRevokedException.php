<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Exception;

final class TokenRevokedException extends AuthorizationTokenException
{
    public static function create(): self
    {
        return new self('The authorization token has been revoked.');
    }
}
