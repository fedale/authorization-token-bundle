<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Exception;

final class TokenConsumedException extends AuthorizationTokenException
{
    public static function create(): self
    {
        return new self('The authorization token has already been consumed.');
    }
}
