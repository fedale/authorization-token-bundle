<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Application\Result;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Non-throwing outcome of a validation attempt.
 *
 * Returned by TokenManager::validate(), which never mutates state. On success
 * the matched token is available; on failure the originating exception is
 * carried so callers can branch on the failure mode without a try/catch.
 */
final class ValidationResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly ?AuthorizationToken $token,
        public readonly ?AuthorizationTokenException $error,
    ) {
    }

    public static function valid(AuthorizationToken $token): self
    {
        return new self(true, $token, null);
    }

    public static function invalid(AuthorizationTokenException $error): self
    {
        return new self(false, null, $error);
    }

    public function reason(): ?string
    {
        return $this->error?->getMessage();
    }
}
