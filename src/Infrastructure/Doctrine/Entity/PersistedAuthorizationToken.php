<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine persistence model for an authorization token.
 *
 * This is intentionally separate from the domain's AuthorizationToken: the
 * core stays free of any ORM concern, and this entity holds only scalar
 * columns. Conversion in both directions is handled by AuthorizationTokenMapper.
 *
 * Not declared final on purpose — Doctrine may need to subclass for proxies.
 */
#[ORM\Entity]
#[ORM\Table(name: 'authorization_token')]
#[ORM\Index(name: 'idx_authz_token_expires_at', columns: ['expires_at'])]
#[ORM\Index(name: 'idx_authz_token_action', columns: ['action'])]
class PersistedAuthorizationToken
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 32)]
    private string $id;

    #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 255, unique: true)]
    private string $tokenHash;

    #[ORM\Column(name: 'action', type: Types::STRING, length: 191)]
    private string $action;

    #[ORM\Column(name: 'subject_type', type: Types::STRING, length: 255, nullable: true)]
    private ?string $subjectType;

    #[ORM\Column(name: 'subject_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $subjectId;

    #[ORM\Column(name: 'issued_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'consumed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $consumedAt;

    #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt;

    #[ORM\Column(name: 'usage_count', type: Types::INTEGER)]
    private int $usageCount;

    #[ORM\Column(name: 'max_usages', type: Types::INTEGER)]
    private int $maxUsages;

    /** @var array<int, array{name: string, parameters: array<string, mixed>}> */
    #[ORM\Column(name: 'constraints', type: Types::JSON)]
    private array $constraints;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'metadata', type: Types::JSON)]
    private array $metadata;

    /**
     * @param array<int, array{name: string, parameters: array<string, mixed>}> $constraints
     * @param array<string, mixed>                                              $metadata
     */
    public function __construct(
        string $id,
        string $tokenHash,
        string $action,
        ?string $subjectType,
        ?string $subjectId,
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $consumedAt,
        ?\DateTimeImmutable $revokedAt,
        int $usageCount,
        int $maxUsages,
        array $constraints,
        array $metadata,
    ) {
        $this->id = $id;
        $this->tokenHash = $tokenHash;
        $this->action = $action;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->issuedAt = $issuedAt;
        $this->expiresAt = $expiresAt;
        $this->consumedAt = $consumedAt;
        $this->revokedAt = $revokedAt;
        $this->usageCount = $usageCount;
        $this->maxUsages = $maxUsages;
        $this->constraints = $constraints;
        $this->metadata = $metadata;
    }

    /**
     * Sync the mutable lifecycle fields from an updated domain token.
     * Identity and issue-time data never change after issuance.
     */
    public function applyState(?\DateTimeImmutable $consumedAt, ?\DateTimeImmutable $revokedAt, int $usageCount): void
    {
        $this->consumedAt = $consumedAt;
        $this->revokedAt = $revokedAt;
        $this->usageCount = $usageCount;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function getMaxUsages(): int
    {
        return $this->maxUsages;
    }

    /**
     * @return array<int, array{name: string, parameters: array<string, mixed>}>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
