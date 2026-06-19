<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\DependencyInjection\Compiler;

use Fedale\AuthorizationTokenBundle\Constraint\Registry\ConstraintValidatorRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects every service tagged "authorization_token.constraint_validator" and
 * injects them, ordered by optional priority, into the registry.
 */
final class RegisterConstraintValidatorsPass implements CompilerPassInterface
{
    public const TAG = 'authorization_token.constraint_validator';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ConstraintValidatorRegistry::class)) {
            return;
        }

        $references = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $priority = (int) ($tags[0]['priority'] ?? 0);
            $references[$priority][] = new Reference($id);
        }

        if ([] === $references) {
            return;
        }

        krsort($references);
        $flattened = array_merge(...$references);

        $container->getDefinition(ConstraintValidatorRegistry::class)
            ->setArgument('$validators', $flattened);
    }
}
