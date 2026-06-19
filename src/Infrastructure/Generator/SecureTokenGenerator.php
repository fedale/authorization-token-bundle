<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Infrastructure\Generator;

use Fedale\AuthorizationTokenBundle\Contract\TokenGeneratorInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;

/**
 * Cryptographically secure, URL-safe token generator.
 *
 * Uses random_bytes() and a base62 alphabet so the resulting token can be
 * placed in a URL or e-mail without any encoding concerns.
 */
final class SecureTokenGenerator implements TokenGeneratorInterface
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function generate(int $length): string
    {
        if ($length < 1) {
            throw new AuthorizationTokenException(sprintf('Token length must be a positive integer, %d given.', $length));
        }

        $alphabetSize = \strlen(self::ALPHABET);
        $token = '';

        for ($i = 0; $i < $length; ++$i) {
            $token .= self::ALPHABET[random_int(0, $alphabetSize - 1)];
        }

        return $token;
    }
}
