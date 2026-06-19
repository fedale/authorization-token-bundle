# Authorization Token Bundle — Documentation

A persistence-agnostic infrastructure for **temporary authorization tokens** in
Symfony.

This document covers the core bundle (`fedale/authorization-token-bundle`) and
the Doctrine ORM persistence adapter
(`fedale/authorization-token-doctrine`).

---

## Table of contents

1. [What it is (and is not)](#1-what-it-is-and-is-not)
2. [Core concepts](#2-core-concepts)
3. [Architecture](#3-architecture)
4. [Installation](#4-installation)
5. [Configuration](#5-configuration)
6. [Public API](#6-public-api)
7. [Token lifecycle](#7-token-lifecycle)
8. [Constraints](#8-constraints)
9. [Events](#9-events)
10. [Persistence](#10-persistence)
11. [Doctrine ORM adapter](#11-doctrine-orm-adapter)
12. [Worked examples](#12-worked-examples)
13. [Building a bundle on top of it](#13-building-a-bundle-on-top-of-it)
14. [Security notes](#14-security-notes)
15. [Testing](#15-testing)
16. [Reference: classes & contracts](#16-reference-classes--contracts)

---

## 1. What it is (and is not)

The bundle manages the **lifecycle** of short-lived tokens:

- issuing a token (and returning the plain value exactly once);
- validating a presented token without changing its state;
- consuming a token (validate, then record a usage);
- revoking a token;
- enforcing per-token constraints;
- tracking expiration and usage allowance.

The bundle deliberately knows **nothing** about the use cases built on top of
it. It does *not* implement, depend on, or reference:

- password reset
- e-mail verification
- magic login
- invitations
- account deletion
- the Symfony Security component
- the `User` entity
- `symfonycasts/reset-password-bundle` or `symfonycasts/verify-email-bundle`

Those are **application concerns**. The bundle treats the *action* as an opaque
string and the *subject* as a plain reference, never a loaded entity.

This separation is what makes the same primitive reusable for password resets,
e-mail confirmations, signed download links, one-time payment confirmations, and
anything else that fits the "issue a secret now, verify it later, once"
pattern.

---

## 2. Core concepts

| Concept | Meaning |
|---|---|
| **Action** | An opaque string identifying *what* the token authorizes, e.g. `user.reset_password`, `account.delete`, `payment.confirm`. The bundle never interprets it. |
| **Subject** | An optional `SubjectReference` (a `type` + `id` pair) pointing at the application object the token concerns. The bundle stores and compares it but never loads it. |
| **Plain token** | The high-entropy secret returned to the caller once at issue time. It is transmitted to the end user (link, e-mail, …) and **never persisted**. |
| **Token hash** | The deterministic fingerprint of the plain token. This is the only token-derived value that ever reaches storage. |
| **TTL** | Time-to-live, after which the token is expired. Resolved per-action or from a global default. |
| **Max usages** | How many times a token may be consumed before it becomes *consumed* (default `1`). |
| **Constraint** | An additional, named validation rule attached at issue time (e.g. bind to an IP) and checked at validation time. |

---

## 3. Architecture

The bundle follows a layered, domain-driven structure. All **public interfaces**
live in `Contract/` (singular); `Infrastructure/` and repository adapters
contain only implementations.

```
src/
├── Contract/         Public interfaces — the stable API surface
│   ├── TokenManagerInterface
│   ├── AuthorizationTokenReadRepositoryInterface     ┐ split read / write
│   ├── AuthorizationTokenWriteRepositoryInterface    ┘ (Interface Segregation)
│   ├── TokenGeneratorInterface
│   ├── TokenHasherInterface
│   └── TokenConstraintValidatorInterface
│
├── Domain/           Pure PHP, no framework / no persistence
│   ├── Model/        AuthorizationToken (aggregate), TokenConstraint, TokenUsage
│   ├── ValueObject/  TokenId, TokenHash, SubjectReference, Expiration
│   ├── Event/        TokenIssued, TokenConsumed, TokenRevoked, TokenExpired
│   └── Exception/    AuthorizationTokenException + specific subtypes
│
├── Application/      Use-case layer
│   ├── Command/      Immutable internal instructions
│   ├── DTO/          Ergonomic public input objects (IssueTokenRequest, …)
│   ├── Service/      TokenManager (facade), TokenIssuer, TokenValidator,
│   │                 TokenConsumer, TokenRevoker
│   └── Result/       IssueResult, ConsumeResult, ValidationResult
│
├── Infrastructure/   Implementations
│   ├── Generator/    SecureTokenGenerator
│   ├── Hashing/      Sha256TokenHasher
│   ├── Clock/        SystemClock (PSR-20)
│   └── EventDispatcher/ DomainEventDispatcher (PSR-14 wrapper)
│
├── Constraint/       ValidationContext + validators + registry
│   ├── ValidationContext
│   ├── Validator/    Ip, UserAgent, Subject, MaxUsage
│   └── Registry/     ConstraintValidatorRegistry
│
└── DependencyInjection/
    ├── AuthorizationTokenExtension
    ├── Configuration
    └── Compiler/RegisterConstraintValidatorsPass
```

### Dependency direction

```
Application ── depends on ──▶ Domain ◀── implements ── Infrastructure
     │                          ▲                           │
     └──────── Contract (interfaces) ◀──────────────────────┘
```

The Domain layer depends on nothing. The Application layer orchestrates Domain
objects and talks to the outside world only through `Contract/` interfaces. The
Infrastructure layer provides concrete implementations of those interfaces.

### Why the model is not a Doctrine entity

`Domain\Model\AuthorizationToken` is a pure object with a private constructor and
two named constructors:

- `AuthorizationToken::issue(...)` — create a brand-new, unused token;
- `AuthorizationToken::reconstitute(...)` — rebuild a token from a persisted
  representation (used by repository adapters).

It owns its lifecycle decisions (`isExpired()`, `isRevoked()`, `isConsumed()`,
`isUsable()`, `recordUsage()`, `revoke()`) and exposes no setters. Persistence
adapters map *to and from* this model rather than annotating it — see
[§10](#10-persistence) and [§11](#11-doctrine-orm-adapter).

---

## 4. Installation

```bash
composer require fedale/authorization-token-bundle
```

Register the bundle (Symfony Flex usually does this automatically):

```php
// config/bundles.php
return [
    // ...
    Fedale\AuthorizationTokenBundle\AuthorizationTokenBundle::class => ['all' => true],
];
```

> **Important:** the core bundle ships **no repository implementation**. On its
> own it cannot boot, because the services need a repository. You must install a
> persistence adapter that binds both
> `AuthorizationTokenReadRepositoryInterface` and
> `AuthorizationTokenWriteRepositoryInterface`. See
> [§11](#11-doctrine-orm-adapter) for the Doctrine ORM adapter.

---

## 5. Configuration

```yaml
# config/packages/authorization_token.yaml
authorization_token:

    generator:
        length: 64            # characters of the generated plain token

    hashing:
        algorithm: sha256     # any algorithm supported by hash()

    defaults:
        ttl: 3600             # seconds, or a relative string like "1 hour"
        max_usages: 1

    actions:                  # per-action overrides, keyed by the action string
        user.reset_password:
            ttl: '30 minutes'
        user.verify_email:
            ttl: '24 hours'
        account.delete:
            ttl: '15 minutes'
            max_usages: 1
```

### TTL resolution order

When issuing a token, the TTL is resolved as:

1. the explicit `ttlSeconds` passed to `issue()` (if any), else
2. the configured `actions.<action>.ttl` (if any), else
3. `defaults.ttl`.

`max_usages` is resolved in the same order. TTL values accept either an integer
number of seconds or a relative time string (`"30 minutes"`, `"24 hours"`),
normalised to seconds at container-compile time.

---

## 6. Public API

Applications depend on a single interface,
`Contract\TokenManagerInterface`, autowired by the bundle.

```php
use Fedale\AuthorizationTokenBundle\Contract\TokenManagerInterface;
use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Domain\Model\TokenConstraint;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\TokenId;
```

### issue()

```php
public function issue(
    string $action,
    ?SubjectReference $subject = null,
    ?int $ttlSeconds = null,
    ?int $maxUsages = null,
    array $constraints = [],   // list<TokenConstraint>
    array $metadata = [],      // array<string, mixed>
): IssueResult;
```

```php
$result = $tokens->issue(
    action: 'user.reset_password',
    subject: new SubjectReference(User::class, '123'),
);

$result->plainToken;   // string — send it NOW, it is never stored again
$result->token;        // AuthorizationToken — the persisted aggregate (hash only)
```

### validate()

Runs every check **without changing state**. Returns a non-throwing
`ValidationResult`.

```php
public function validate(
    string $token,
    string $action,
    ?ValidationContext $context = null,
): ValidationResult;
```

```php
$validation = $tokens->validate($plainToken, 'user.reset_password');

if ($validation->valid) {
    $token = $validation->token;       // AuthorizationToken
} else {
    $reason = $validation->reason();   // human-readable failure message
    $error  = $validation->error;      // the AuthorizationTokenException subtype
}
```

### consume()

Validates and then records a usage, persisting the new state.

```php
public function consume(
    string $token,
    string $action,
    ?ValidationContext $context = null,
): ConsumeResult;
```

```php
$outcome = $tokens->consume($plainToken, 'user.reset_password');

$outcome->fullyConsumed;   // true when this usage exhausted the allowance
$outcome->remainingUses(); // int — for multi-use tokens
```

Unlike `validate()`, `consume()` **throws** on failure (the same exception types
listed in [§7](#7-token-lifecycle)), because a failed consumption is an
exceptional flow.

### revoke()

```php
public function revoke(TokenId $tokenId): void;
```

```php
$tokens->revoke($result->token->getId());
```

### Request DTOs (alternative input)

For controllers that build a structured request object, `TokenManager` also
accepts DTOs:

```php
use Fedale\AuthorizationTokenBundle\Application\DTO\IssueTokenRequest;
use Fedale\AuthorizationTokenBundle\Application\DTO\ConsumeTokenRequest;

$tokens->issueRequest(new IssueTokenRequest(action: 'user.verify_email', subject: $ref));
$tokens->consumeRequest(new ConsumeTokenRequest($plainToken, 'user.verify_email'));
```

---

## 7. Token lifecycle

```
                       issue()
                          │
                          ▼
                    ┌───────────┐   validate()  (no state change)
                    │  USABLE   │◀───────────────────────────────
                    └───────────┘
                       │      │
              consume()│      │ revoke()
                       ▼      ▼
            ┌──────────────┐ ┌───────────┐
            │  CONSUMED    │ │  REVOKED  │
            │ (allowance   │ └───────────┘
            │  exhausted)  │
            └──────────────┘

  Orthogonally, once `now >= expiresAt` the token is EXPIRED regardless of state.
```

### Checks performed (in order) by `validate()` / `consume()`

1. **Hash lookup** — the presented plain token is hashed and looked up. No match
   → `TokenNotFoundException`.
2. **Action match** — a token issued for a different action is reported as
   *not found* (`TokenNotFoundException`), so the existence of a token for
   another action is never leaked.
3. **Revocation** — `TokenRevokedException`.
4. **Consumption** — `TokenConsumedException`.
5. **Expiration** — `TokenExpiredException` (also dispatches `TokenExpired`).
6. **Constraints** — each attached constraint is validated; the first failure
   raises `ConstraintViolationException`.

`consume()` runs the exact same checks (it reuses the validator), so a token can
never be consumed unless it would also validate. On success it calls
`recordUsage()`: the usage counter increments and, when the allowance is
reached, the token becomes *consumed* as of "now".

All exceptions extend `Domain\Exception\AuthorizationTokenException`.

---

## 8. Constraints

A **constraint** is an additional, named rule attached to a token at issue time
and checked during validation. Built-in validators:

| Name | Issue with | Checked against |
|---|---|---|
| `ip` | `new TokenConstraint('ip', ['ip' => $request->getClientIp()])` | `ValidationContext::$ip` |
| `user_agent` | `new TokenConstraint('user_agent', ['user_agent' => $ua])` | `ValidationContext::$userAgent` |
| `subject` | `new TokenConstraint('subject')` | the `subject` key in `ValidationContext::$attributes` (compared to the token's bound subject) |
| `max_usage` | `new TokenConstraint('max_usage', ['max' => 3])` | the token's current usage count |

### Passing runtime context

```php
use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;

$context = new ValidationContext(
    ip: $request->getClientIp(),
    userAgent: $request->headers->get('User-Agent'),
    attributes: ['subject' => new SubjectReference(User::class, (string) $userId)],
);

$tokens->consume($plainToken, 'account.delete', $context);
```

### Writing a custom constraint validator

Implement `Contract\TokenConstraintValidatorInterface`. With autoconfiguration
enabled the service is tagged automatically
(`authorization_token.constraint_validator`) and collected into the registry by
a compiler pass — no manual wiring.

```php
use Fedale\AuthorizationTokenBundle\Constraint\ValidationContext;
use Fedale\AuthorizationTokenBundle\Contract\TokenConstraintValidatorInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\ConstraintViolationException;
use Fedale\AuthorizationTokenBundle\Domain\Model\AuthorizationToken;

final class TenantConstraintValidator implements TokenConstraintValidatorInterface
{
    public function supports(string $constraint): bool
    {
        return 'tenant' === $constraint;
    }

    public function validate(AuthorizationToken $token, ValidationContext $context): void
    {
        $expected = $token->getConstraint('tenant')?->get('tenant_id');
        $actual   = $context->attribute('tenant_id');

        if ($expected !== null && $expected !== $actual) {
            throw ConstraintViolationException::forConstraint('tenant', 'tenant mismatch.');
        }
    }
}
```

Attach it when issuing:

```php
$tokens->issue('organization.invite_member', constraints: [
    new TokenConstraint('tenant', ['tenant_id' => 42]),
]);
```

You can influence validator order with the tag `priority` attribute (higher runs
first) if you register the service explicitly.

---

## 9. Events

Domain events are dispatched on the PSR-14 dispatcher (Symfony's
`event_dispatcher`). Subscribe with the standard
`#[AsEventListener]` attribute or an event subscriber.

| Event | Dispatched when | Carries |
|---|---|---|
| `Domain\Event\TokenIssued` | a token has been generated and persisted | `->token` |
| `Domain\Event\TokenConsumed` | a usage has been recorded | `->token` |
| `Domain\Event\TokenRevoked` | a token has been revoked | `->token` |
| `Domain\Event\TokenExpired` | an expired token is presented | `->token` |

```php
use Fedale\AuthorizationTokenBundle\Domain\Event\TokenConsumed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class AuditTokenConsumption
{
    #[AsEventListener]
    public function onConsumed(TokenConsumed $event): void
    {
        // $event->token->getAction(), $event->token->getSubject(), ...
    }
}
```

For `TokenConsumed`, inspect `$event->token->isConsumed()` to tell a single-use
token that has just been exhausted from a multi-use token with remaining uses.

---

## 10. Persistence

The core depends only on two contracts:

```php
interface AuthorizationTokenReadRepositoryInterface
{
    public function findByHash(TokenHash $hash): ?AuthorizationToken;
    public function findById(TokenId $id): ?AuthorizationToken;
}

interface AuthorizationTokenWriteRepositoryInterface
{
    public function save(AuthorizationToken $token): void;
    public function remove(AuthorizationToken $token): void;
}
```

They are split (read vs write) so that consumers depend only on the capability
they need. A single adapter class typically implements both.

Adapters reconstruct the domain model through
`AuthorizationToken::reconstitute(...)`, which accepts the full persisted state
(including `consumedAt`, `revokedAt`, `usageCount`). Available / planned
adapters:

- `fedale/authorization-token-doctrine` — Doctrine ORM (documented below)
- `authorization-token-mongodb` — Doctrine MongoDB ODM
- `authorization-token-redis` — Redis with native TTL

---

## 11. Doctrine ORM adapter

`fedale/authorization-token-doctrine` provides Doctrine ORM persistence.

### Design

The domain model stays free of ORM concerns. The adapter introduces a separate
persistence entity with scalar columns and a mapper that converts in both
directions.

- `Entity\PersistedAuthorizationToken` — the mapped entity (attribute mapping).
- `Mapper\AuthorizationTokenMapper` — domain ⇄ entity conversion.
- `Repository\DoctrineTokenRepository` — implements both core contracts; flushes
  on write so `save()`/`remove()` are immediately durable.

### Column mapping

| Domain | Column | Type |
|---|---|---|
| `TokenId` | `id` (PK) | `string(32)` |
| `TokenHash` | `token_hash` (unique) | `string(255)` |
| `action` | `action` (indexed) | `string(191)` |
| `SubjectReference` | `subject_type`, `subject_id` | `string` nullable |
| `issuedAt` | `issued_at` | `datetime_immutable` |
| `expiresAt` | `expires_at` (indexed) | `datetime_immutable` |
| `consumedAt` | `consumed_at` | `datetime_immutable` nullable |
| `revokedAt` | `revoked_at` | `datetime_immutable` nullable |
| `usageCount` | `usage_count` | `integer` |
| `maxUsages` | `max_usages` | `integer` |
| `constraints` | `constraints` | `json` |
| `metadata` | `metadata` | `json` |

### Installation

```bash
composer require fedale/authorization-token-doctrine
```

```php
// config/bundles.php
return [
    Fedale\AuthorizationTokenBundle\AuthorizationTokenBundle::class => ['all' => true],
    Fedale\AuthorizationTokenDoctrine\AuthorizationTokenDoctrineBundle::class => ['all' => true],
];
```

That is all the wiring required. The adapter:

- **prepends** its attribute mapping to the default Doctrine ORM entity manager
  (no `doctrine.orm.mappings` entry needed in the app);
- **aliases** both core repository contracts to `DoctrineTokenRepository`.

From then on `TokenManagerInterface` is fully operational.

### Schema / migrations

Once the bundle is registered, the entity mapping is known to Doctrine, so the
table is generated like any other entity:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Reference DDL (PostgreSQL):

```sql
CREATE TABLE authorization_token (
    id           VARCHAR(32)  NOT NULL,
    token_hash   VARCHAR(255) NOT NULL,
    action       VARCHAR(191) NOT NULL,
    subject_type VARCHAR(255) DEFAULT NULL,
    subject_id   VARCHAR(255) DEFAULT NULL,
    issued_at    TIMESTAMP(0) NOT NULL,
    expires_at   TIMESTAMP(0) NOT NULL,
    consumed_at  TIMESTAMP(0) DEFAULT NULL,
    revoked_at   TIMESTAMP(0) DEFAULT NULL,
    usage_count  INT          NOT NULL,
    max_usages   INT          NOT NULL,
    constraints  JSON         NOT NULL,
    metadata     JSON         NOT NULL,
    PRIMARY KEY (id)
);
CREATE UNIQUE INDEX uniq_authz_token_hash ON authorization_token (token_hash);
CREATE INDEX idx_authz_token_expires_at ON authorization_token (expires_at);
CREATE INDEX idx_authz_token_action ON authorization_token (action);
```

### Expired-token housekeeping

Expired tokens are not removed automatically. Schedule a periodic cleanup
(`DELETE FROM authorization_token WHERE expires_at < NOW()`), or subscribe to
`TokenExpired` to react on access. The `expires_at` index supports efficient
bulk deletes.

---

## 12. Worked examples

### Password reset

```php
final class PasswordResetService
{
    public function __construct(
        private readonly TokenManagerInterface $tokens,
        private readonly MailerInterface $mailer,
    ) {}

    public function request(User $user): void
    {
        $result = $this->tokens->issue(
            action: 'user.reset_password',
            subject: new SubjectReference(User::class, (string) $user->getId()),
        );

        $link = $this->urlGenerator->generate('reset_password_confirm', [
            'token' => $result->plainToken,   // the only time we ever see it
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // send $link to $user->getEmail()
    }

    public function confirm(string $plainToken, string $newPassword): void
    {
        // throws on invalid/expired/used token
        $outcome = $this->tokens->consume($plainToken, 'user.reset_password');

        $subject = $outcome->token->getSubject();   // User#123
        // load the user by $subject->id and set the new password
    }
}
```

### E-mail verification (longer TTL, configured per action)

```php
$result = $tokens->issue(
    action: 'user.verify_email',                    // ttl: 24 hours (from config)
    subject: new SubjectReference(User::class, $id),
    metadata: ['email' => $pendingEmail],           // arbitrary app data
);
// later:
$outcome = $tokens->consume($plainToken, 'user.verify_email');
$verifiedEmail = $outcome->token->getMetadata()['email'];
```

### IP-bound, single-use account deletion

```php
$result = $tokens->issue(
    action: 'account.delete',
    subject: new SubjectReference(User::class, $id),
    ttlSeconds: 900,                                          // 15 minutes
    constraints: [new TokenConstraint('ip', ['ip' => $request->getClientIp()])],
);

// confirmation must come from the same IP
$context = new ValidationContext(ip: $request->getClientIp());
$tokens->consume($plainToken, 'account.delete', $context);
```

### Non-throwing pre-check before showing a form

```php
$validation = $tokens->validate($plainToken, 'user.reset_password');

if (!$validation->valid) {
    return $this->render('reset/invalid.html.twig', ['reason' => $validation->reason()]);
}
// render the "set new password" form; consume only on submit
```

---

## 13. Building a bundle on top of it

This is the intended way to consume the package: a higher-level, use-case bundle
(say a `PasswordRecoveryBundle`) treats `authorization-token-bundle` as its
token engine and adds the domain-specific logic the engine deliberately leaves
out (user lookup, e-mail templates, routes, forms).

### Ground rules

1. **Depend on the contract, not the implementation.** Your bundle requires
   `fedale/authorization-token-bundle` in `composer.json` and type-hints
   `Contract\TokenManagerInterface`. That is the entire integration surface.
2. **Own your action strings.** Namespace them so they never collide with other
   consumers, e.g. `password_recovery.reset`. The engine treats them as opaque.
3. **Do not choose a persistence adapter for the application.** Your bundle
   stays storage-agnostic, exactly like the engine. Document that the app must
   install one adapter (Doctrine/MongoDB/Redis). Optionally check at runtime and
   fail with a clear message if no repository is bound.
4. **Do not force global configuration.** You may *prepend* sensible defaults
   (e.g. the TTL for your action) so the app works out of the box, while the app
   can still override them.
5. **Don't reference the user entity in the engine's terms.** Convert your
   domain object to a `SubjectReference` at the boundary, and back when consuming.

### composer.json of the downstream bundle

```json
{
    "name": "acme/password-recovery-bundle",
    "type": "symfony-bundle",
    "require": {
        "php": ">=8.2",
        "fedale/authorization-token-bundle": "^1.0",
        "symfony/framework-bundle": "^6.4 || ^7.0"
    }
}
```

Note it requires **only the core bundle**, never an adapter — the application
picks the adapter.

### A service that wraps the engine

```php
namespace Acme\PasswordRecoveryBundle\Service;

use Fedale\AuthorizationTokenBundle\Contract\TokenManagerInterface;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;
use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;

final class PasswordRecovery
{
    /** The single action string this bundle owns. */
    private const ACTION = 'password_recovery.reset';

    public function __construct(
        private readonly TokenManagerInterface $tokens,
        private readonly UserRepository $users,           // YOUR domain
        private readonly RecoveryMailer $mailer,          // YOUR concern
    ) {}

    public function request(string $email): void
    {
        $user = $this->users->findOneByEmail($email);

        if (null === $user) {
            return; // never reveal whether the address exists
        }

        $result = $this->tokens->issue(
            action: self::ACTION,
            subject: new SubjectReference(User::class, (string) $user->getId()),
        );

        // plain token leaves the system exactly once, here
        $this->mailer->sendResetLink($user, $result->plainToken);
    }

    /**
     * @throws AuthorizationTokenException when the token is invalid/expired/used
     */
    public function reset(string $plainToken, string $newPassword): void
    {
        $outcome = $this->tokens->consume($plainToken, self::ACTION);

        $userId = $outcome->token->getSubject()?->id;
        $user   = $this->users->get((int) $userId);

        // ... hash and persist $newPassword on $user ...
    }
}
```

The downstream bundle never touches hashing, expiration, storage, or events —
only its own `request()` / `reset()` semantics.

### Shipping default TTL via prepend

Let the engine work without the app having to configure your action. In your
bundle's extension:

```php
namespace Acme\PasswordRecoveryBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

final class AcmePasswordRecoveryExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // load your own services.yaml ...
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('authorization_token')) {
            return;
        }

        // Provide a default TTL for our action; the app can still override it.
        $container->prependExtensionConfig('authorization_token', [
            'actions' => [
                'password_recovery.reset' => ['ttl' => '30 minutes'],
            ],
        ]);
    }
}
```

Because `prependExtensionConfig` is *prepended* (lowest precedence), an explicit
value in the application's `authorization_token.yaml` wins. The app installs the
three bundles and a storage adapter, and everything is wired:

```php
// config/bundles.php (application side)
return [
    Fedale\AuthorizationTokenBundle\AuthorizationTokenBundle::class => ['all' => true],
    Fedale\AuthorizationTokenDoctrine\AuthorizationTokenDoctrineBundle::class => ['all' => true],
    Acme\PasswordRecoveryBundle\AcmePasswordRecoveryBundle::class => ['all' => true],
];
```

### Reacting to engine events

Your bundle can subscribe to the engine's events for cross-cutting behaviour
without coupling — e.g. invalidate previous reset tokens when a new one is
issued, or audit consumption:

```php
use Fedale\AuthorizationTokenBundle\Domain\Event\TokenConsumed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class RecoveryAuditListener
{
    #[AsEventListener]
    public function onConsumed(TokenConsumed $event): void
    {
        if ('password_recovery.reset' === $event->token->getAction()) {
            // log / notify
        }
    }
}
```

### What stays where — summary

| Concern | Engine (`authorization-token-bundle`) | Your bundle (`PasswordRecoveryBundle`) | Application |
|---|---|---|---|
| Generate / hash / store / expire token | ✅ | — | — |
| Action string & its TTL default | — | ✅ (owns name, prepends TTL) | may override TTL |
| User lookup, e-mail, routes, forms | — | ✅ | — |
| Persistence adapter choice | — | — | ✅ |
| `SubjectReference` ⇄ `User` mapping | — | ✅ | — |

---

## 14. Security notes

- **Plain token is never stored.** It exists only in the `IssueResult` returned
  by `issue()`. Persist nothing but the hash; transmit the plain value
  immediately and discard it.
- **High entropy.** The default 64-character base62 token carries well over 256
  bits of entropy, generated with `random_int()`. A fast hash (sha256) is
  appropriate precisely because the input is high-entropy random data — unlike
  passwords, it is not subject to brute force and does not need a slow KDF.
- **Constant-time comparison.** Hash comparison uses `hash_equals()`.
- **No enumeration leak.** Unknown tokens and action mismatches both surface as
  `TokenNotFoundException`.
- **Bind to context where it matters.** For sensitive actions, attach `ip` /
  `user_agent` constraints and/or keep `max_usages` at `1` with a short TTL.
- **Revoke proactively.** When the underlying reason disappears (e.g. the user
  changed their password through another path), revoke outstanding tokens.

---

## 15. Testing

### Core bundle

Unit tests construct the services directly (no container) using a
`MutableClock` and an in-memory repository:

```bash
cd authorization-token-bundle
composer install
vendor/bin/phpunit
```

### Doctrine adapter

The adapter suite runs against an in-memory SQLite database, creating the schema
from the entity mapping, so it exercises real mapping and hydration:

```bash
cd authorization-token-doctrine
composer install
vendor/bin/phpunit
```

### Testing your own code

In application tests you can inject a fake repository implementing the two
contracts and a frozen `Psr\Clock\ClockInterface`, then drive
`TokenManagerInterface` directly — no database required.

---

## 16. Reference: classes & contracts

### Contracts (`Contract/`)

| Interface | Purpose |
|---|---|
| `TokenManagerInterface` | The public facade (`issue`, `validate`, `consume`, `revoke`). |
| `AuthorizationTokenReadRepositoryInterface` | `findByHash`, `findById`. |
| `AuthorizationTokenWriteRepositoryInterface` | `save`, `remove`. |
| `TokenGeneratorInterface` | Produces the plain token. Default: `SecureTokenGenerator`. |
| `TokenHasherInterface` | `hash`, `verify`. Default: `Sha256TokenHasher`. |
| `TokenConstraintValidatorInterface` | Extension point for custom constraints. |

### Domain model (`Domain/Model/`)

| Class | Notes |
|---|---|
| `AuthorizationToken` | Aggregate root. Named constructors `issue()` / `reconstitute()`; lifecycle methods `isUsable()`, `recordUsage()`, `revoke()`, etc. |
| `TokenConstraint` | `name` + `parameters` (opaque to the bundle). |
| `TokenUsage` | Usage snapshot (`count`, `max`, `isExhausted()`, `remaining()`). |

### Value objects (`Domain/ValueObject/`)

`TokenId`, `TokenHash`, `SubjectReference` (`type#id`), `Expiration`.

### Application services (`Application/Service/`)

| Service | Responsibility |
|---|---|
| `TokenManager` | Public facade; builds commands and delegates. |
| `TokenIssuer` | Resolve TTL/usages, generate, hash, persist, emit `TokenIssued`. |
| `TokenValidator` | `assert()` (throwing) + `validate()` (non-throwing). |
| `TokenConsumer` | Validate, record usage, persist, emit `TokenConsumed`. |
| `TokenRevoker` | Load by id, revoke, persist, emit `TokenRevoked`. |

### Results (`Application/Result/`)

| Result | Fields |
|---|---|
| `IssueResult` | `plainToken`, `token` |
| `ConsumeResult` | `token`, `fullyConsumed`, `remainingUses()` |
| `ValidationResult` | `valid`, `token`, `error`, `reason()` |

### Exceptions (`Domain/Exception/`)

`AuthorizationTokenException` (base) → `TokenNotFoundException`,
`TokenExpiredException`, `TokenRevokedException`, `TokenConsumedException`,
`ConstraintViolationException`.

---

*Bundle namespace:* `Fedale\AuthorizationTokenBundle` ·
*Doctrine adapter namespace:* `Fedale\AuthorizationTokenDoctrine` ·
*License:* MIT.
