<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Mapper;

use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Domain\Model\TokenConstraint;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;
use Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Entity\PersistedAuthorizationToken;

/**
 * Translates between the pure domain AuthorizationToken and its Doctrine
 * persistence model.
 *
 * Reconstruction uses AuthorizationToken::reconstitute(), the named
 * constructor the core exposes specifically for repository adapters.
 */
final class AuthorizationTokenMapper
{
    public function toEntity(AuthorizationToken $token): PersistedAuthorizationToken
    {
        $subject = $token->getSubject();

        return new PersistedAuthorizationToken(
            $token->getId()->value,
            $token->getHash()->value,
            $token->getAction(),
            $subject?->type,
            $subject?->id,
            $token->getIssuedAt(),
            $token->getExpiresAt(),
            $token->getConsumedAt(),
            $token->getRevokedAt(),
            $token->getUsageCount(),
            $token->getMaxUsages(),
            $this->encodeConstraints($token->getConstraints()),
            $token->getMetadata(),
        );
    }

    public function applyState(PersistedAuthorizationToken $entity, AuthorizationToken $token): void
    {
        $entity->applyState($token->getConsumedAt(), $token->getRevokedAt(), $token->getUsageCount());
    }

    public function toDomain(PersistedAuthorizationToken $entity): AuthorizationToken
    {
        $subject = null;

        if (null !== $entity->getSubjectType() && null !== $entity->getSubjectId()) {
            $subject = new SubjectReference($entity->getSubjectType(), $entity->getSubjectId());
        }

        return AuthorizationToken::reconstitute(
            TokenId::fromString($entity->getId()),
            TokenHash::fromString($entity->getTokenHash()),
            $entity->getAction(),
            $subject,
            $entity->getIssuedAt(),
            $entity->getExpiresAt(),
            $entity->getConsumedAt(),
            $entity->getRevokedAt(),
            $entity->getUsageCount(),
            $entity->getMaxUsages(),
            $this->decodeConstraints($entity->getConstraints()),
            $entity->getMetadata(),
        );
    }

    /**
     * @param list<TokenConstraint> $constraints
     *
     * @return array<int, array{name: string, parameters: array<string, mixed>}>
     */
    private function encodeConstraints(array $constraints): array
    {
        return array_map(
            static fn (TokenConstraint $constraint): array => [
                'name' => $constraint->name,
                'parameters' => $constraint->parameters,
            ],
            $constraints,
        );
    }

    /**
     * @param array<int, array{name: string, parameters?: array<string, mixed>}> $rows
     *
     * @return list<TokenConstraint>
     */
    private function decodeConstraints(array $rows): array
    {
        return array_map(
            static fn (array $row): TokenConstraint => new TokenConstraint($row['name'], $row['parameters'] ?? []),
            array_values($rows),
        );
    }
}
