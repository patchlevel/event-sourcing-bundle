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

        // @codingStandardsIgnoreStart
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->arrayNode('connection')
                ->children()
                    ->scalarNode('service')->defaultNull()->end()
                    ->scalarNode('url')->defaultNull()->end()
                ->end()
            ->end()

            ->arrayNode('store')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('merge_orm_schema')->defaultFalse()->end()
                ->end()
            ->end()

            ->arrayNode('event_bus')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('type')
                        ->values(['default', 'symfony', 'psr14', 'custom'])
                        ->defaultValue('default')
                    ->end()
                    ->scalarNode('service')->defaultNull()->end()
                ->end()
            ->end()

            ->arrayNode('events')
                ->beforeNormalization()->castToArray()->end()
                ->defaultValue([])
                ->scalarPrototype()->end()
            ->end()

            ->arrayNode('aggregates')
                ->beforeNormalization()->castToArray()->end()
                ->defaultValue([])
                ->scalarPrototype()->end()
            ->end()

            ->arrayNode('clock')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('freeze')->defaultNull()->end()
                    ->scalarNode('service')->defaultNull()->end()
                ->end()
            ->end()

            ->arrayNode('watch_server')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->children()
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
                        ->enumNode('type')->values(['psr6', 'psr16', 'custom'])->defaultValue('psr6')->end()
                        ->scalarNode('service')->end()
                    ->end()
                ->end()
            ->end()

            ->arrayNode('projection')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('sync')->defaultValue(true)->end()
                    ->booleanNode('auto_boot')->defaultValue(false)->end()
                    ->booleanNode('auto_recovery')->defaultValue(false)->end()
                    ->booleanNode('auto_teardown')->defaultValue(false)->end()
                ->end()
            ->end()

            ->arrayNode('ui')
                ->canBeEnabled()
            ->end()

        ->end();
        // @codingStandardsIgnoreEnd

        return $treeBuilder;
    }
}
