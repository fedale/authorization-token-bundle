# Authorization Token Bundle

Persistence-agnostic infrastructure for **temporary authorization tokens** in
Symfony.

The bundle manages the *lifecycle* of short-lived tokens — issuing, validating,
consuming, revoking, plus expiration and constraints. It deliberately knows
**nothing** about the use cases built on top of it (password reset, e-mail
verification, magic login, invitations, account deletion, …). Those are
application concerns; the bundle treats the *action* as an opaque string and
the *subject* as a plain reference.

## Design principles

- **Domain-driven**, single responsibility per service.
- **Persistence-agnostic** — the core depends only on repository *interfaces*;
  Doctrine/MongoDB/Redis adapters live in separate packages.
- **No dependency** on the Security component, the User entity, or any
  reset-password / verify-email bundle.
- **Hash-only storage** — the plain token is returned once and never persisted.
- **Event-driven** — `TokenIssued`, `TokenConsumed`, `TokenRevoked`,
  `TokenExpired` are dispatched on the PSR-14 dispatcher.
- **Extensible** — add your own constraint validators via a tagged service.

## Layout

```
src/
├── Contract/         Public interfaces (the stable API surface)
├── Domain/           Model, value objects, events, exceptions (pure PHP)
├── Application/      Commands, DTOs, services, results (the use-case layer)
├── Infrastructure/   Generator, hasher, clock, event dispatcher (implementations)
├── Constraint/       ValidationContext, validators, registry
└── DependencyInjection/
```

All public interfaces live in `Contract/` (singular). `Infrastructure/` and the
future `Repository/` adapters contain only implementations.

## Storage

The bundle ships a built-in **Doctrine ORM** storage adapter. It is wired
automatically — and only — when DoctrineBundle is present in the kernel: the
extension registers the `PersistedAuthorizationToken` entity mapping and binds
both `AuthorizationTokenReadRepositoryInterface` and
`AuthorizationTokenWriteRepositoryInterface` to the Doctrine repository. Run
`composer require doctrine/orm` (and `doctrine/doctrine-bundle`) to enable it,
then generate the schema with `doctrine:migrations:diff`.

Without Doctrine the core stays fully usable as a persistence-agnostic library:
it depends only on the repository *interfaces* (`Contract/`). To use a different
storage, implement those two interfaces (a single class may implement both) and
bind them in your application — re-aliasing them in your own `services.yaml`
overrides the built-in Doctrine binding, since the last definition wins.

## Configuration

```yaml
# config/packages/authorization_token.yaml
authorization_token:
    generator:
        length: 64
    hashing:
        algorithm: sha256
    defaults:
        ttl: 3600          # seconds, or "1 hour"
        max_usages: 1
    actions:
        user.reset_password:
            ttl: '30 minutes'
        user.verify_email:
            ttl: '24 hours'
        account.delete:
            ttl: '15 minutes'
```

## Usage

```php
use Fedale\AuthorizationTokenBundle\Contract\TokenManagerInterface;
use Fedale\AuthorizationTokenBundle\Domain\ValueObject\SubjectReference;

final class PasswordResetController
{
    public function __construct(private readonly TokenManagerInterface $tokens) {}

    public function request(): void
    {
        $result = $this->tokens->issue(
            action: 'user.reset_password',
            subject: new SubjectReference(User::class, '123'),
        );

        // $result->plainToken — send it now (link/e-mail), it is never stored again.
    }

    public function confirm(string $plainToken): void
    {
        $outcome = $this->tokens->consume($plainToken, 'user.reset_password');

        if ($outcome->fullyConsumed) {
            // proceed with the password change
        }
    }
}
```

`validate()` performs the same checks without changing state and returns a
non-throwing `ValidationResult`; `consume()` validates and then records a usage.

## Custom constraints

Implement `Contract\TokenConstraintValidatorInterface`. With autoconfiguration
enabled the service is tagged automatically and collected into the registry:

```php
final class TenantConstraintValidator implements TokenConstraintValidatorInterface
{
    public function supports(string $constraint): bool
    {
        return 'tenant' === $constraint;
    }

    public function validate(AuthorizationToken $token, ValidationContext $context): void
    {
        // throw ConstraintViolationException on mismatch
    }
}
```

Attach it when issuing: `new TokenConstraint('tenant', ['tenant_id' => 42])`.

## Tests

```bash
composer install
vendor/bin/phpunit
```
