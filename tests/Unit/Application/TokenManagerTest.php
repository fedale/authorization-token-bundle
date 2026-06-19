<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Tests\Unit\Application;

use Fedale\AuthorizationTokenBundle\Application\Service\TokenConsumer;
use Fedale\AuthorizationTokenBundle\Application\Service\TokenIssuer;
use Fedale\AuthorizationTokenBundle\Application\Service\TokenManager;
use Fedale\AuthorizationTokenBundle\Application\Service\TokenRevoker;
use Fedale\AuthorizationTokenBundle\Application\Service\TokenValidator;
use Fedale\AuthorizationTokenBundle\Constraint\Registry\ConstraintValidatorRegistry;
use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Constraint\Validator\IpConstraintValidator;
use Fedale\AuthorizationTokenBundle\Domain\Event\TokenConsumed;
use Fedale\AuthorizationTokenBundle\Domain\Event\TokenIssued;
use Fedale\AuthorizationTokenBundle\Domain\Model\TokenConstraint;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;
use Fedale\AuthorizationTokenBundle\Infrastructure\EventDispatcher\DomainEventDispatcher;
use Fedale\AuthorizationTokenBundle\Infrastructure\Generator\SecureTokenGenerator;
use Fedale\AuthorizationTokenBundle\Infrastructure\Hashing\Sha256TokenHasher;
use Fedale\AuthorizationTokenBundle\Tests\Support\InMemoryTokenRepository;
use Fedale\AuthorizationTokenBundle\Tests\Support\MutableClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TokenManagerTest extends TestCase
{
    private InMemoryTokenRepository $repository;
    private MutableClock $clock;
    private EventDispatcher $symfonyDispatcher;
    private TokenManager $manager;

    protected function setUp(): void
    {
        $this->repository = new InMemoryTokenRepository();
        $this->clock = new MutableClock();
        $this->symfonyDispatcher = new EventDispatcher();

        $events = new DomainEventDispatcher($this->symfonyDispatcher);
        $hasher = new Sha256TokenHasher();
        $registry = new ConstraintValidatorRegistry([new IpConstraintValidator()]);

        $issuer = new TokenIssuer(
            new SecureTokenGenerator(),
            $hasher,
            $this->repository,
            $this->clock,
            $events,
            generatorLength: 64,
            defaultTtlSeconds: 1800,
            defaultMaxUsages: 1,
            actions: ['user.verify_email' => ['ttl' => 86400]],
        );
        $validator = new TokenValidator($hasher, $this->repository, $this->clock, $registry, $events);
        $consumer = new TokenConsumer($validator, $this->repository, $this->clock, $events);
        $revoker = new TokenRevoker($this->repository, $this->repository, $this->clock, $events);

        $this->manager = new TokenManager($issuer, $validator, $consumer, $revoker);
    }

    public function testIssueReturnsPlainTokenAndPersistsOnlyHash(): void
    {
        $issued = [];
        $this->symfonyDispatcher->addListener(TokenIssued::class, function (TokenIssued $e) use (&$issued): void {
            $issued[] = $e->token;
        });

        $result = $this->manager->issue('user.reset_password', new SubjectReference('App\\User', '123'));

        self::assertNotSame('', $result->plainToken);
        self::assertCount(1, $issued);
        // The stored token never carries the plain value.
        self::assertNotSame($result->plainToken, $result->token->getHash()->value);
    }

    public function testValidateSucceedsForAFreshTokenWithoutChangingState(): void
    {
        $result = $this->manager->issue('user.reset_password');

        $validation = $this->manager->validate($result->plainToken, 'user.reset_password');

        self::assertTrue($validation->valid);
        self::assertFalse($result->token->isConsumed(), 'validate() must not mutate state');
    }

    public function testValidateFailsOnActionMismatch(): void
    {
        $result = $this->manager->issue('user.reset_password');

        $validation = $this->manager->validate($result->plainToken, 'account.delete');

        self::assertFalse($validation->valid);
    }

    public function testConsumeRecordsUsageAndDispatchesEvent(): void
    {
        $consumed = [];
        $this->symfonyDispatcher->addListener(TokenConsumed::class, function (TokenConsumed $e) use (&$consumed): void {
            $consumed[] = $e->token;
        });

        $result = $this->manager->issue('user.reset_password');
        $outcome = $this->manager->consume($result->plainToken, 'user.reset_password');

        self::assertTrue($outcome->fullyConsumed);
        self::assertCount(1, $consumed);

        // A single-use token cannot be consumed twice.
        $second = $this->manager->validate($result->plainToken, 'user.reset_password');
        self::assertFalse($second->valid);
    }

    public function testExpiredTokenDoesNotValidate(): void
    {
        $result = $this->manager->issue('user.reset_password', ttlSeconds: 600);

        $this->clock->advance('+11 minutes');

        self::assertFalse($this->manager->validate($result->plainToken, 'user.reset_password')->valid);
    }

    public function testIpConstraintIsEnforced(): void
    {
        $result = $this->manager->issue(
            'account.delete',
            constraints: [new TokenConstraint('ip', ['ip' => '203.0.113.7'])],
        );

        $wrongIp = $this->manager->validate($result->plainToken, 'account.delete', new ValidationContext(ip: '198.51.100.1'));
        self::assertFalse($wrongIp->valid);

        $rightIp = $this->manager->validate($result->plainToken, 'account.delete', new ValidationContext(ip: '203.0.113.7'));
        self::assertTrue($rightIp->valid);
    }

    public function testRevokedTokenDoesNotValidate(): void
    {
        $result = $this->manager->issue('user.reset_password');

        $this->manager->revoke($result->token->getId());

        self::assertFalse($this->manager->validate($result->plainToken, 'user.reset_password')->valid);
    }
}
