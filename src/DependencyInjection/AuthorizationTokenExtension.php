<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Loads service definitions and turns the processed configuration into
 * container parameters consumed by the services.
 *
 * The built-in Doctrine storage adapter is wired only when DoctrineBundle is
 * present in the kernel, so the core stays installable and bootable without
 * Doctrine. Applications can override the repository bindings to plug a
 * different storage.
 */
final class AuthorizationTokenExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        // Wire the built-in Doctrine adapter only when DoctrineBundle is enabled.
        // load() runs inside a temporary container during the merge pass where
        // hasExtension('doctrine') is unreliable, so detect via kernel.bundles.
        if ($this->isDoctrineOrmEnabled($container)) {
            $loader->load('services_doctrine.yaml');
        }

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('authorization_token.generator.length', $config['generator']['length']);
        $container->setParameter('authorization_token.hashing.algorithm', $config['hashing']['algorithm']);
        $container->setParameter('authorization_token.defaults.ttl', $this->normalizeTtl($config['defaults']['ttl']));
        $container->setParameter('authorization_token.defaults.max_usages', $config['defaults']['max_usages']);
        $container->setParameter('authorization_token.actions', $this->normalizeActions($config['actions']));
    }

    /**
     * @param array<string, array{ttl: int|string|null, max_usages: int|null}> $actions
     *
     * @return array<string, array{ttl?: int, max_usages?: int}>
     */
    private function normalizeActions(array $actions): array
    {
        $normalized = [];

        foreach ($actions as $name => $action) {
            $entry = [];

            if (null !== $action['ttl']) {
                $entry['ttl'] = $this->normalizeTtl($action['ttl']);
            }

            if (null !== $action['max_usages']) {
                $entry['max_usages'] = $action['max_usages'];
            }

            $normalized[$name] = $entry;
        }

        return $normalized;
    }

    /**
     * Accept an integer number of seconds or a relative time string.
     */
    private function normalizeTtl(int|string $ttl): int
    {
        if (\is_int($ttl)) {
            $seconds = $ttl;
        } elseif (ctype_digit($ttl)) {
            $seconds = (int) $ttl;
        } else {
            $seconds = strtotime($ttl, 0);

            if (false === $seconds) {
                throw new AuthorizationTokenException(sprintf('Cannot parse TTL "%s" as a duration.', $ttl));
            }
        }

        if ($seconds <= 0) {
            throw new AuthorizationTokenException(sprintf('TTL must resolve to a positive number of seconds, got %d.', $seconds));
        }

        return $seconds;
    }

    /**
     * Whether the built-in Doctrine adapter should be wired: doctrine/orm must
     * be installed and DoctrineBundle enabled in the kernel. The kernel.bundles
     * parameter is reliable inside the merge-pass temporary container, unlike
     * hasExtension().
     */
    private function isDoctrineOrmEnabled(ContainerBuilder $container): bool
    {
        if (!interface_exists(EntityManagerInterface::class)) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

        return isset($bundles['DoctrineBundle']);
    }

    /**
     * Register the Doctrine attribute mapping for the persistence entity, but
     * only when DoctrineBundle is present so the core remains optional.
     */
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'AuthorizationTokenBundle' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__).'/Infrastructure/Doctrine/Entity',
                        'prefix' => 'Fedale\\AuthorizationTokenBundle\\Infrastructure\\Doctrine\\Entity',
                        'is_bundle' => false,
                        'alias' => 'AuthorizationTokenBundle',
                    ],
                ],
            ],
        ]);
    }

    public function getAlias(): string
    {
        return 'authorization_token';
    }
}
