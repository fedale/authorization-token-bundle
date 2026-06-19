<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Contract;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

/**
 * Write side of token persistence.
 *
 * Split from the read contract so that consumers depend only on the capability
 * they actually need (Interface Segregation). A concrete adapter typically
 * implements both interfaces with a single class.
 */
interface AuthorizationTokenWriteRepositoryInterface
{
    public function save(AuthorizationToken $token): void;

    public function remove(AuthorizationToken $token): void;
}
