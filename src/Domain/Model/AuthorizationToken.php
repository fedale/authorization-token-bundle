<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Model;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;

/**
 * A temporary authorization grant.
 *
 * This is the aggregate root. It owns its lifecycle decisions (expired,
 * revoked, consumed, usable) and the rules for recording a usage or being
 * revoked. It knows nothing about persistence, the Security component or any
 * concrete use case — the action is an opaque string and the subject is a
 * plain reference, never a loaded entity.
 */
final class AuthorizationToken
{
    /**
     * @param list<TokenConstraint> $constraints
     * @param array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly TokenId $id,
        private readonly TokenHash $hash,
        private readonly string $action,
        private readonly ?SubjectReference $subject,
        private readonly \DateTimeImmutable $issuedAt,
        private readonly \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $consumedAt,
        private ?\DateTimeImmutable $revokedAt,
        private int $usageCount,
        private readonly int $maxUsages,
        private readonly array $constraints,
        private readonly array $metadata,
    ) {
        if ($action === '') {
            throw new AuthorizationTokenException('A token action cannot be empty.');
        }

        if ($maxUsages < 1) {
            throw new AuthorizationTokenException('maxUsages must be at least 1.');
        }
    }

    /**
     * Create a brand-new, unused token.
     *
     * @param list<TokenConstraint> $constraints
     * @param array<string, mixed>  $metadata
     */
    public static function issue(
        TokenId $id,
        TokenHash $hash,
        string $action,
        ?SubjectReference $subject,
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $expiresAt,
        int $maxUsages,
        array $constraints = [],
        array $metadata = [],
    ): self {
        return new self(
            $id,
            $hash,
            $action,
            $subject,
            $issuedAt,
            $expiresAt,
            null,
            null,
            0,
            $maxUsages,
            array_values($constraints),
            $metadata,
        );
    }

    /**
     * Rebuild a token from a persisted representation. Used by repository
     * adapters during hydration — no domain events, no validation of history.
     *
     * @param list<TokenConstraint> $constraints
     * @param array<string, mixed>  $metadata
     */
    public static function reconstitute(
        TokenId $id,
        TokenHash $hash,
        string $action,
        ?SubjectReference $subject,
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $consumedAt,
        ?\DateTimeImmutable $revokedAt,
        int $usageCount,
        int $maxUsages,
        array $constraints = [],
        array $metadata = [],
    ): self {
        return new self(
            $id,
            $hash,
            $action,
            $subject,
            $issuedAt,
            $expiresAt,
            $consumedAt,
            $revokedAt,
            $usageCount,
            $maxUsages,
            array_values($constraints),
            $metadata,
        );
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    /**
     * Usable means: not expired, not revoked, not consumed and with at least
     * one remaining use.
     */
    public function isUsable(\DateTimeImmutable $now): bool
    {
        return !$this->isExpired($now)
            && !$this->isRevoked()
            && !$this->isConsumed()
            && $this->usageCount < $this->maxUsages;
    }

    /**
     * Record a single use. When the usage allowance is reached the token
     * becomes consumed as of $now.
     */
    public function recordUsage(\DateTimeImmutable $now): void
    {
        if ($this->isConsumed()) {
            throw new AuthorizationTokenException('Cannot record usage on a consumed token.');
        }

        if ($this->isRevoked()) {
            throw new AuthorizationTokenException('Cannot record usage on a revoked token.');
        }

        ++$this->usageCount;

        if ($this->usageCount >= $this->maxUsages) {
            $this->consumedAt = $now;
        }
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        if ($this->isRevoked()) {
            return;
        }

        $this->revokedAt = $now;
    }

    public function getUsage(): TokenUsage
    {
        return new TokenUsage($this->usageCount, $this->maxUsages);
    }

    public function getConstraint(string $name): ?TokenConstraint
    {
        foreach ($this->constraints as $constraint) {
            if ($constraint->name === $name) {
                return $constraint;
            }
        }

        return null;
    }

    public function getId(): TokenId
    {
        return $this->id;
    }

    public function getHash(): TokenHash
    {
        return $this->hash;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getSubject(): ?SubjectReference
    {
        return $this->subject;
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
     * @return list<TokenConstraint>
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
