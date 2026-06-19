<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Tests\Integration\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;
use Fedale\AuthorizationTokenBundle\Domain\Model\TokenConstraint;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenHash;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;
use Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Mapper\AuthorizationTokenMapper;
use Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Repository\DoctrineTokenRepository;
use Fedale\AuthorizationTokenBundle\Tests\Support\EntityManagerFactory;
use PHPUnit\Framework\TestCase;

final class DoctrineTokenRepositoryTest extends TestCase
{
    private EntityManagerInterface $em;
    private DoctrineTokenRepository $repository;

    protected function setUp(): void
    {
        $this->em = EntityManagerFactory::create();
        $this->repository = new DoctrineTokenRepository($this->em, new AuthorizationTokenMapper());
    }

    public function testSaveThenFindByHashReconstitutesTheFullToken(): void
    {
        $token = $this->makeToken(
            subject: new SubjectReference('App\\Entity\\User', '123'),
            constraints: [new TokenConstraint('ip', ['ip' => '203.0.113.7'])],
            metadata: ['locale' => 'it'],
        );

        $this->repository->save($token);
        $this->em->clear(); // force hydration from the database

        $found = $this->repository->findByHash($token->getHash());

        self::assertInstanceOf(AuthorizationToken::class, $found);
        self::assertTrue($found->getId()->equals($token->getId()));
        self::assertSame('user.reset_password', $found->getAction());
        self::assertNotNull($found->getSubject());
        self::assertTrue($found->getSubject()->equals(new SubjectReference('App\\Entity\\User', '123')));
        self::assertSame('203.0.113.7', $found->getConstraint('ip')?->get('ip'));
        self::assertSame('it', $found->getMetadata()['locale']);
    }

    public function testFindByHashReturnsNullForUnknownToken(): void
    {
        self::assertNull($this->repository->findByHash(new TokenHash('nope')));
    }

    public function testConsumeStateIsPersistedOnUpdate(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $token = $this->makeToken();
        $this->repository->save($token);

        $token->recordUsage($now);
        $this->repository->save($token);
        $this->em->clear();

        $reloaded = $this->repository->findById($token->getId());

        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isConsumed());
        self::assertSame(1, $reloaded->getUsageCount());
    }

    public function testRevokeStateIsPersistedOnUpdate(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $token = $this->makeToken();
        $this->repository->save($token);

        $token->revoke($now);
        $this->repository->save($token);
        $this->em->clear();

        $reloaded = $this->repository->findById($token->getId());

        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isRevoked());
    }

    public function testRemoveDeletesTheToken(): void
    {
        $token = $this->makeToken();
        $this->repository->save($token);

        $this->repository->remove($token);
        $this->em->clear();

        self::assertNull($this->repository->findById($token->getId()));
    }

    /**
     * @param list<TokenConstraint> $constraints
     * @param array<string, mixed>  $metadata
     */
    private function makeToken(
        ?SubjectReference $subject = null,
        array $constraints = [],
        array $metadata = [],
    ): AuthorizationToken {
        $issuedAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        return AuthorizationToken::issue(
            TokenId::generate(),
            new TokenHash(hash('sha256', bin2hex(random_bytes(8)))),
            'user.reset_password',
            $subject,
            $issuedAt,
            $issuedAt->modify('+30 minutes'),
            1,
            $constraints,
            $metadata,
        );
    }
}
