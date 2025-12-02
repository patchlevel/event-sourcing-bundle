<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Throwable;

/**
 * @psalm-type Config = array{
 *      event_bus: array{enabled: bool, type: string, service: string},
 *      command_bus: array{
 *          enabled: bool,
 *          service: string,
 *          instant_retry: array{
 *              default_max_retries: positive-int|0,
 *              default_exceptions: list<class-string<Throwable>>
 *          },
 *      },
 *      query_bus: array{enabled: bool, service: string},
 *      subscription: array{
 *          store: array{type: string, service: string|null},
 *          retry_strategy?: array{base_delay: int, delay_factor: int, max_attempts: int},
 *          retry_strategies: array<string, array{type: string, service: string, options: array<string, mixed>}>,
 *          default_retry_strategy: string,
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
 *          rebuild_after_file_change: array{enabled: bool, cache_pool: string},
 *          gap_detection: array{
 *              enabled: bool,
 *              retries_in_ms: list<int>,
 *              detection_window: string
 *          }
 *      },
 *      connection: ?array{
 *          service: ?string,
 *          url: ?string,
 *          provide_dedicated_connection: bool
 *      },
 *      store: array{
 *          merge_orm_schema: bool,
 *          options: array<string, mixed>,
 *          type: string,
 *          service: ?string,
 *          read_only: bool,
 *          migrate_to_new_store: array{
 *              enabled: bool,
 *              type: string,
 *              service: ?string,
 *              options: array<string, mixed>,
 *              translators: list<string>
 *          }
 *      },
 *      aggregates: list<string>,
 *      events: list<string>,
 *      headers: list<string>,
 *      snapshot_stores: array<string, array{type: string, service: string}>,
 *      migration: array{path: string, namespace: string},
 *      cryptography: array{
 *          enabled: bool,
 *          algorithm: string,
 *          use_encrypted_field_name: bool,
 *          fallback_to_field_name: bool,
 *      },
 *      clock: array{freeze: ?string, service: ?string},
 *      aggregate_handlers: array{enabled: bool, bus: string|null},
 * }
 */
final class Configuration implements ConfigurationInterface
{
    /** @return TreeBuilder<'array'> */
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
                    ->booleanNode('provide_dedicated_connection')->defaultFalse()->end()
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
                    ->booleanNode('read_only')->defaultFalse()->end()
                    ->arrayNode('migrate_to_new_store')
                        ->canBeEnabled()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('type')
                                ->values(['dbal_aggregate', 'dbal_stream', 'in_memory', 'custom'])
                            ->end()
                            ->scalarNode('service')->defaultNull()->end()
                            ->arrayNode('options')->variablePrototype()->end()->end()
                            ->arrayNode('translators')->scalarPrototype()->end()->end()
                        ->end()
                    ->end()
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
                    ->arrayNode('store')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('type')
                                ->values(['dbal', 'in_memory', 'static_in_memory', 'custom'])
                                ->defaultValue('dbal')
                            ->end()
                            ->scalarNode('service')->defaultNull()->end()
                        ->end()
                    ->end()

                    ->arrayNode('retry_strategy')
                        ->setDeprecated(
                            'patchlevel/event-sourcing-bundle',
                            '3.10',
                            'The "%node%" option is deprecated and will be removed in 4.0. Use "patchlevel_event_sourcing.subscription.retry_strategies" instead.'
                        )
                        ->children()
                            ->integerNode('base_delay')->defaultValue(5)->end()
                            ->integerNode('delay_factor')->defaultValue(2)->end()
                            ->integerNode('max_attempts')->defaultValue(5)->end()
                        ->end()
                    ->end()

                    ->arrayNode('retry_strategies')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->enumNode('type')->values(['clock_based', 'no_retry', 'custom'])->end()
                                ->scalarNode('service')->end()
                                ->arrayNode('options')->variablePrototype()->end()->end()
                            ->end()
                        ->end()
                        ->defaultValue([
                            'default' => [
                                'type' => 'clock_based',
                                'options' => [
                                    'base_delay' => 5,
                                    'delay_factor' => 2,
                                    'max_attempts' => 5,
                                ],
                            ],
                            'no_retry' => [
                                'type' => 'no_retry',
                            ],
                        ])
                    ->end()

                    ->scalarNode('default_retry_strategy')->defaultValue('default')->end()

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

                    ->arrayNode('rebuild_after_file_change')
                        ->canBeEnabled()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('cache_pool')->defaultValue('cache.app')->end()
                        ->end()
                    ->end()

                    ->arrayNode('gap_detection')
                        ->canBeEnabled()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('retries_in_ms')
                                ->scalarPrototype()->end()
                                ->defaultValue([0, 5, 50, 500])
                            ->end()
                            ->scalarNode('detection_window')->defaultValue('PT5M')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()

            ->arrayNode('cryptography')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('algorithm')->defaultValue('aes256')->end()
                    ->booleanNode('use_encrypted_field_name')->defaultFalse()->end()
                    ->booleanNode('fallback_to_field_name')->defaultFalse()->end()
                ->end()
            ->end()

            ->arrayNode('command_bus')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('service')->isRequired()->end()
                    ->booleanNode('register_aggregate_handlers')->defaultTrue()->end()
                    ->arrayNode('instant_retry')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('default_max_retries')
                                ->defaultValue(3)
                            ->end()
                            ->arrayNode('default_exceptions')
                                ->defaultValue([AggregateOutdated::class])
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()

            ->arrayNode('query_bus')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('service')->isRequired()->end()
                ->end()
            ->end()

            ->arrayNode('aggregate_handlers')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('bus')->defaultNull()->end()
                ->end()
                ->setDeprecated(
                    'patchlevel/event-sourcing-bundle',
                    '3.9',
                    'The "%node%" option is deprecated and will be removed in 4.0. Use "patchlevel_event_sourcing.command_bus" instead.'
                )
            ->end()

        ->end();
        // @codingStandardsIgnoreEnd

        return $treeBuilder;
    }
}
