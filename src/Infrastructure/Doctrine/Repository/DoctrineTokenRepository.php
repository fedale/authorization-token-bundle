<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenReadRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Contract\AuthorizationTokenWriteRepositoryInterface;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;
use Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Entity\PersistedAuthorizationToken;
use Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Mapper\AuthorizationTokenMapper;

/**
 * Doctrine ORM implementation of the token repository contracts.
 *
 * A single class fulfils both the read and write sides. It flushes on write so
 * callers (the core services) can treat save()/remove() as immediately
 * durable, matching the contract's expectations.
 */
final class DoctrineTokenRepository implements
    AuthorizationTokenReadRepositoryInterface,
    AuthorizationTokenWriteRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationTokenMapper $mapper,
    ) {
    }

    public function save(AuthorizationToken $token): void
    {
        $entity = $this->entityManager->find(PersistedAuthorizationToken::class, $token->getId()->value);

        if (null === $entity) {
            $this->entityManager->persist($this->mapper->toEntity($token));
        } else {
            $this->mapper->applyState($entity, $token);
        }

        $this->entityManager->flush();
    }

    public function remove(AuthorizationToken $token): void
    {
        $entity = $this->entityManager->find(PersistedAuthorizationToken::class, $token->getId()->value);

        if (null === $entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function findByHash(TokenHash $hash): ?AuthorizationToken
    {
        $entity = $this->entityManager
            ->getRepository(PersistedAuthorizationToken::class)
            ->findOneBy(['tokenHash' => $hash->value]);

        return $entity instanceof PersistedAuthorizationToken ? $this->mapper->toDomain($entity) : null;
    }

    public function findById(TokenId $id): ?AuthorizationToken
    {
        $entity = $this->entityManager->find(PersistedAuthorizationToken::class, $id->value);

        return $entity instanceof PersistedAuthorizationToken ? $this->mapper->toDomain($entity) : null;
    }
}
