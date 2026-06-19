<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for the authorization_token root key.
 *
 * TTL values accept either an integer number of seconds or a relative time
 * string ("30 minutes", "24 hours"); the extension normalises them to seconds.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('authorization_token');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('generator')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('length')
                            ->defaultValue(64)
                            ->min(1)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('hashing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('algorithm')
                            ->defaultValue('sha256')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('defaults')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('ttl')
                            ->info('Default time-to-live: integer seconds or a relative string like "1 hour".')
                            ->defaultValue(3600)
                        ->end()
                        ->integerNode('max_usages')
                            ->defaultValue(1)
                            ->min(1)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('actions')
                    ->info('Per-action overrides keyed by the opaque action string.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('ttl')
                                ->info('Integer seconds or a relative string like "30 minutes".')
                                ->defaultNull()
                            ->end()
                            ->integerNode('max_usages')
                                ->defaultNull()
                                ->min(1)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
