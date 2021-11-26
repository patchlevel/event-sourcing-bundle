<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
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
            ->isRequired()
            ->cannotBeEmpty()
            ->end()

            ->arrayNode('aggregates')
            ->useAttributeAsKey('class')
            ->scalarPrototype()->end()
            ->end()

            ->arrayNode('watch_server')
            ->addDefaultsIfNotSet()
            ->children()

            ->booleanNode('enabled')->defaultValue(false)->end()

            ->scalarNode('host')->defaultValue('127.0.0.1:5000')->end()

            ->end()
            ->end()

            ->end();

        return $treeBuilder;
    }
}
