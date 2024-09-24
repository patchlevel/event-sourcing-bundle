<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @psalm-type Config = array{
 *      event_bus: array{enabled: bool, type: string, service: string},
 *      subscription: array{
 *          retry_strategy: array{base_delay: int, delay_factor: int, max_attempts: int},
 *          catch_up: array{enabled: bool, limit: positive-int|null},
 *          throw_on_error: array{enabled: bool},
 *          run_after_aggregate_save: array{
 *              enabled: bool,
 *              ids: list<string>,
 *              groups: list<string>,
 *              limit: positive-int|null
 *          },
 *          auto_setup: array{
 *               enabled: bool,
 *               ids: list<string>,
 *               groups: list<string>,
 *           },
 *          rebuild_after_file_change: bool
 *      },
 *      connection: ?array{service: ?string, url: ?string},
 *      store: array{merge_orm_schema: bool, options: array<string, mixed>, type: string, service: ?string},
 *      aggregates: list<string>,
 *      events: list<string>,
 *      headers: list<string>,
 *      snapshot_stores: array<string, array{type: string, service: string}>,
 *      migration: array{path: string, namespace: string},
 *      cryptography: array{enabled: bool, algorithm: string},
 *      clock: array{freeze: ?string, service: ?string},
 *      debug: array{trace: bool}
 * }
 */
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
                    ->enumNode('type')
                        ->values(['dbal_aggregate', 'dbal_stream', 'in_memory', 'custom'])
                        ->defaultValue('dbal_aggregate')
                    ->end()
                    ->scalarNode('service')->defaultNull()->end()
                    ->booleanNode('merge_orm_schema')->defaultFalse()->end()
                    ->arrayNode('options')->variablePrototype()->end()->end()
                ->end()
                ->validate()
                    ->ifTrue(function (array $v) {
                        return $v['type'] === 'custom' && empty($v['service']);
                    })
                    ->thenInvalid('The "service" field is required when "type" is set to "custom".')
                ->end()
            ->end()

            ->arrayNode('event_bus')
                ->canBeEnabled()
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

            ->arrayNode('headers')
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

            ->arrayNode('subscription')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('retry_strategy')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('base_delay')->defaultValue(5)->end()
                            ->integerNode('delay_factor')->defaultValue(2)->end()
                            ->integerNode('max_attempts')->defaultValue(5)->end()
                        ->end()
                    ->end()

                    ->arrayNode('catch_up')
                        ->canBeEnabled()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('limit')->defaultNull()->end()
                        ->end()
                    ->end()

                    ->arrayNode('throw_on_error')
                        ->canBeEnabled()
                    ->end()

                    ->arrayNode('run_after_aggregate_save')
                        ->canBeEnabled()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('ids')->scalarPrototype()->end()->end()
                            ->arrayNode('groups')->scalarPrototype()->end()->end()
                            ->integerNode('limit')->defaultNull()->end()
                        ->end()
                    ->end()

                    ->arrayNode('auto_setup')
                        ->canBeEnabled()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('ids')->scalarPrototype()->end()->end()
                            ->arrayNode('groups')->scalarPrototype()->end()->end()
                        ->end()
                    ->end()

                    ->booleanNode('rebuild_after_file_change')->defaultFalse()->end()
                ->end()
            ->end()

            ->arrayNode('cryptography')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('algorithm')->defaultValue('aes256')->end()
                ->end()
            ->end()

            ->arrayNode('debug')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('trace')->defaultFalse()->end()
                ->end()
            ->end()
        ->end();
        // @codingStandardsIgnoreEnd

        return $treeBuilder;
    }
}
