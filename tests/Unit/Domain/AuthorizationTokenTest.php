<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Tests\Unit\Domain;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;
use PHPUnit\Framework\TestCase;

final class AuthorizationTokenTest extends TestCase
{
    private const NOW = '2026-01-01T00:00:00+00:00';

    public function testFreshTokenIsUsable(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $token = $this->makeToken($now, maxUsages: 1);

        self::assertTrue($token->isUsable($now));
        self::assertFalse($token->isConsumed());
        self::assertFalse($token->isRevoked());
        self::assertSame(1, $token->getUsage()->remaining());
    }

    public function testSingleUseTokenBecomesConsumedAfterOneUsage(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $token = $this->makeToken($now, maxUsages: 1);

        $token->recordUsage($now);

        self::assertTrue($token->isConsumed());
        self::assertFalse($token->isUsable($now));
        self::assertSame($now, $token->getConsumedAt());
    }

    public function testMultiUseTokenStaysUsableUntilAllowanceExhausted(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $token = $this->makeToken($now, maxUsages: 3);

        $token->recordUsage($now);
        self::assertFalse($token->isConsumed());
        self::assertSame(2, $token->getUsage()->remaining());

        $token->recordUsage($now);
        $token->recordUsage($now);
        self::assertTrue($token->isConsumed());
    }

    public function testExpiryIsRelativeToProvidedClock(): void
    {
        $issuedAt = new \DateTimeImmutable(self::NOW);
        $token = $this->makeToken($issuedAt, maxUsages: 1, ttlSeconds: 1800);

        self::assertFalse($token->isExpired($issuedAt->modify('+29 minutes')));
        self::assertTrue($token->isExpired($issuedAt->modify('+31 minutes')));
    }

    public function testConsumingARevokedTokenIsRejected(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $token = $this->makeToken($now, maxUsages: 1);

        $token->revoke($now);

        self::assertTrue($token->isRevoked());
        $this->expectException(AuthorizationTokenException::class);
        $token->recordUsage($now);
    }

    private function makeToken(\DateTimeImmutable $issuedAt, int $maxUsages, int $ttlSeconds = 3600): AuthorizationToken
    {
        return AuthorizationToken::issue(
            TokenId::generate(),
            new TokenHash('hash-'.bin2hex(random_bytes(4))),
            'user.reset_password',
            null,
            $issuedAt,
            $issuedAt->modify(sprintf('+%d seconds', $ttlSeconds)),
            $maxUsages,
        );
    }
}
