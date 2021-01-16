<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('patchlevel_event_sourcing');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->scalarNode('message_bus')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->arrayNode('aggregates')
            ->useAttributeAsKey('class')
            ->scalarPrototype()->end()
            ->end()
            ->enumNode('storage_type')
            ->values(['single_table', 'multi_table'])
            ->defaultValue('multi_table')
            ->end()
            ->scalarNode('dbal_connection')
            ->defaultValue('default')
            ->end()
            ->end();

        return $treeBuilder;
    }
}
