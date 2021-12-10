<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress MixedMethodCall
     * @psalm-suppress PossiblyUndefinedMethod
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('patchlevel_event_sourcing');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->arrayNode('store')
            ->addDefaultsIfNotSet()
            ->children()

            ->enumNode('type')
            ->values(['dbal_single_table', 'dbal_multi_table'])
            ->defaultValue('dbal_single_table')
            ->end()

            ->scalarNode('dbal_connection')
            ->defaultValue('default')
            ->end()

            ->scalarNode('schema_manager')->defaultNull()->end()

            ->end()
            ->end()

            ->scalarNode('message_bus')
            ->defaultNull()
            ->end()

            ->arrayNode('aggregates')
            ->useAttributeAsKey('name')

            ->arrayPrototype()
            ->children()
            ->scalarNode('class')->end()
            ->scalarNode('snapshot_store')->defaultNull()->end()
            ->end()
            ->end()

            ->end()

            ->arrayNode('watch_server')
            ->addDefaultsIfNotSet()
            ->children()

            ->booleanNode('enabled')->defaultValue(false)->end()

            ->scalarNode('host')->defaultValue('127.0.0.1:5000')->end()

            ->end()
            ->end()

            ->arrayNode('migration')
            ->addDefaultsIfNotSet()
            ->children()

            ->scalarNode('namespace')->defaultValue('EventSourcingMigrations')->end()
            ->scalarNode('path')->defaultValue('%kernel.project_dir%/migrations')->end()

            ->end()
            ->end()

            ->arrayNode('snapshot_stores')
            ->useAttributeAsKey('name')

            ->arrayPrototype()
            ->children()
            ->enumNode('type')->values(['psr6', 'psr16', 'custom'])->end()
            ->scalarNode('id')->end()
            ->end()
            ->end()

            ->end()

            ->end();

        return $treeBuilder;
    }
}
