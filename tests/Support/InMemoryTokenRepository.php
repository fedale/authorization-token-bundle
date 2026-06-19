<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Tests\Support;

use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenReadRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenWriteRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;

/**
 * Volatile repository used by the test-suite. The production adapter is the
 * Doctrine implementation shipped in Infrastructure\Doctrine.
 */
final class InMemoryTokenRepository implements AuthorizationTokenReadRepositoryInterface, AuthorizationTokenWriteRepositoryInterface
{
    /** @var array<string, AuthorizationToken> keyed by token id */
    private array $tokens = [];

    public function save(AuthorizationToken $token): void
    {
        $this->tokens[$token->getId()->value] = $token;
    }

    public function remove(AuthorizationToken $token): void
    {
        unset($this->tokens[$token->getId()->value]);
    }

    public function findByHash(TokenHash $hash): ?AuthorizationToken
    {
        foreach ($this->tokens as $token) {
            if ($token->getHash()->equals($hash)) {
                return $token;
            }
        }

        return null;
    }

    public function findById(TokenId $id): ?AuthorizationToken
    {
        return $this->tokens[$id->value] ?? null;
    }
}
