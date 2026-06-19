<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Contract;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;

/**
 * Read side of token persistence.
 *
 * The bundle ships a Doctrine implementation
 * (Infrastructure\Doctrine\Repository\DoctrineTokenRepository), auto-wired when
 * DoctrineBundle is present. The core depends only on this contract and never
 * on any storage technology, so any other adapter can be bound instead.
 */
interface AuthorizationTokenReadRepositoryInterface
{
    public function findByHash(TokenHash $hash): ?AuthorizationToken;

    public function findById(TokenId $id): ?AuthorizationToken;
}
