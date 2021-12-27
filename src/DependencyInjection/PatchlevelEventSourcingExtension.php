<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionCreateCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\SnapshotRepository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Schema\MigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\WatchServer\DefaultWatchServer;
use Patchlevel\EventSourcing\WatchServer\DefaultWatchServerClient;
use Patchlevel\EventSourcing\WatchServer\WatchListener;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use Patchlevel\EventSourcingBundle\DataCollector\EventCollector;
use Patchlevel\EventSourcingBundle\DataCollector\EventListener;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

use function class_exists;
use function sprintf;

final class PatchlevelEventSourcingExtension extends Extension
{
    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        /**
         * @var array{event_bus: ?array{type: string, service: string}, watch_server: array{enabled: bool, host: string}, connection: ?array{service: ?string, url: ?string}, store: array{schema_manager: string, type: string, options: array<string, mixed>}, aggregates: array<string, array{class: string, snapshot_store: ?string}>, snapshot_stores: array<string, array{type: string, service: string}>, migration: array{path: string, namespace: string}} $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        if (!isset($config['connection'])) {
            return;
        }

        $this->configureEventBus($config, $container);
        $this->configureProjection($container);
        $this->configureConnection($config, $container);
        $this->configureStorage($config, $container);
        $this->configureSnapshots($config, $container);
        $this->configureAggregates($config, $container);
        $this->configureCommands($container);
        $this->configureProfiler($container);

        if (class_exists(DependencyFactory::class)) {
            $this->configureMigration($config, $container);
        }

        if (!$config['watch_server']['enabled']) {
            return;
        }

        $this->configureWatchServer($config, $container);
    }

    /**
     * @param array{event_bus: ?array{type: string, service: string}} $config
     */
    private function configureEventBus(array $config, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(Listener::class)
            ->addTag('event_sourcing.processor');

        if (!isset($config['event_bus'])) {
            $container->register(DefaultEventBus::class);
            $container->setAlias(EventBus::class, DefaultEventBus::class);

            return;
        }

        if ($config['event_bus']['type'] === 'symfony') {
            $container->register(SymfonyEventBus::class)
                ->setArguments([new Reference($config['event_bus']['service'])]);

            $container->setAlias(EventBus::class, SymfonyEventBus::class);
            $container->setParameter('event_sourcing.event_bus_service', $config['event_bus']['service']);

            return;
        }

        $container->register($config['event_bus']['service'], EventBus::class);
        $container->setAlias(EventBus::class, $config['event_bus']['service']);
    }

    private function configureProjection(ContainerBuilder $container): void
    {
        $container->register(ProjectionListener::class)
            ->setArguments([new Reference(ProjectionRepository::class)])
            ->addTag('event_sourcing.processor', ['priority' => -32]);

        $container->registerForAutoconfiguration(Projection::class)
            ->addTag('event_sourcing.projection');

        $container->register(DefaultProjectionRepository::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.projection')]);

        $container->setAlias(ProjectionRepository::class, DefaultProjectionRepository::class);
    }

    /**
     * @param array{connection: array{url: ?string, service: ?string}} $config
     */
    private function configureConnection(array $config, ContainerBuilder $container): void
    {
        if ($config['connection']['url']) {
            $container->register('event_sourcing.dbal_connection', Connection::class)
                ->setFactory([DriverManager::class, 'getConnection'])
                ->setArguments([
                    [
                        'url' => $config['connection']['url'],
                    ],
                ]);

            return;
        }

        if (!$config['connection']['service']) {
            return;
        }

        $container->setAlias('event_sourcing.dbal_connection', $config['connection']['service']);
    }

    /**
     * @param array{store: array{schema_manager: string, type: string, options: array<string, mixed>}} $config
     */
    private function configureStorage(array $config, ContainerBuilder $container): void
    {
        if ($config['store']['schema_manager']) {
            $container->setAlias(SchemaManager::class, $config['store']['schema_manager']);
        } else {
            $container->register(DoctrineSchemaManager::class);
            $container->setAlias(SchemaManager::class, DoctrineSchemaManager::class);
        }

        if ($config['store']['type'] === 'single_table') {
            $container->register(SingleTableStore::class)
                ->setArguments([
                    new Reference('event_sourcing.dbal_connection'),
                    '%event_sourcing.aggregates%',
                    $config['store']['options']['table_name'] ?? 'eventstore',
                ]);

            $container->setAlias(Store::class, SingleTableStore::class);

            return;
        }

        if ($config['store']['type'] !== 'multi_table') {
            return;
        }

        $container->register(MultiTableStore::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
                '%event_sourcing.aggregates%',
                $config['store']['options']['table_name'] ?? 'eventstore',
            ]);

        $container->setAlias(Store::class, MultiTableStore::class);
    }

    /**
     * @param array{snapshot_stores: array<string, array{type: string, service: string}>} $config
     */
    private function configureSnapshots(array $config, ContainerBuilder $container): void
    {
        foreach ($config['snapshot_stores'] as $name => $definition) {
            $id = sprintf('event_sourcing.snapshot_store.%s', $name);

            if ($definition['type'] === 'psr6') {
                $container->register($id, Psr6SnapshotStore::class)
                    ->setArguments([new Reference($definition['service'])]);

                continue;
            }

            if ($definition['type'] === 'psr16') {
                $container->register($id, Psr16SnapshotStore::class)
                    ->setArguments([new Reference($definition['service'])]);

                continue;
            }

            $container->setAlias($id, $definition['service']);
        }
    }

    /**
     * @param array{aggregates: array<string, array{class: string, snapshot_store: ?string}>} $config
     */
    private function configureAggregates(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('event_sourcing.aggregates', $this->aggregateHashMap($config['aggregates']));

        foreach ($config['aggregates'] as $aggregateName => $definition) {
            $id = sprintf('event_sourcing.repository.%s', $aggregateName);

            if ($definition['snapshot_store']) {
                $container->register($id, SnapshotRepository::class)
                    ->setArguments([
                        new Reference(Store::class),
                        new Reference(EventBus::class),
                        $definition['class'],
                        new Reference(
                            sprintf('event_sourcing.snapshot_store.%s', $definition['snapshot_store'])
                        ),
                    ])
                    ->setPublic(true);
            } else {
                $container->register($id, DefaultRepository::class)
                    ->setArguments([
                        new Reference(Store::class),
                        new Reference(EventBus::class),
                        $definition['class'],
                    ])
                    ->setPublic(true);
            }

            $container->registerAliasForArgument($id, Repository::class, $aggregateName . 'Repository');
        }
    }

    private function configureCommands(ContainerBuilder $container): void
    {
        $container->register(DoctrineHelper::class);

        $container->register(DatabaseCreateCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(DoctrineHelper::class),
            ])
            ->addTag('console.command');

        $container->register(DatabaseDropCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(DoctrineHelper::class),
            ])
            ->addTag('console.command');

        $container->register(SchemaCreateCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(SchemaManager::class),
            ])
            ->addTag('console.command');

        $container->register(SchemaUpdateCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(SchemaManager::class),
            ])
            ->addTag('console.command');

        $container->register(SchemaDropCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(SchemaManager::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionCreateCommand::class)
            ->setArguments([
                new Reference(ProjectionRepository::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionDropCommand::class)
            ->setArguments([
                new Reference(ProjectionRepository::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionRebuildCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(ProjectionRepository::class),
            ])
            ->addTag('console.command');

        $container->register(ShowCommand::class)
            ->setArguments([
                new Reference(Store::class),
                '%event_sourcing.aggregates%',
            ])
            ->addTag('console.command');
    }

    /**
     * @param array{watch_server: array{host: string}} $config
     */
    private function configureWatchServer(array $config, ContainerBuilder $container): void
    {
        $container->register(DefaultWatchServerClient::class)
            ->setArguments([$config['watch_server']['host']]);

        $container->setAlias(WatchServerClient::class, DefaultWatchServerClient::class);

        $container->register(WatchListener::class)
            ->setArguments([new Reference(WatchServerClient::class)])
            ->addTag('event_sourcing.processor');

        $container->register(DefaultWatchServer::class)
            ->setArguments([$config['watch_server']['host']]);

        $container->setAlias(WatchServer::class, DefaultWatchServer::class);

        $container->register(WatchCommand::class)
            ->setArguments([
                new Reference(WatchServer::class),
            ])
            ->addTag('console.command');
    }

    /**
     * @param array{migration: array{path: string, namespace: string}} $config
     */
    private function configureMigration(array $config, ContainerBuilder $container): void
    {
        $container->register('event_sourcing.migration.configuration', ConfigurationArray::class)
            ->setArguments([
                [
                    'migrations_paths' => [$config['migration']['namespace'] => $config['migration']['path']],
                ],
            ]);

        $container->register('event_sourcing.migration.connection', ExistingConnection::class)
            ->setArguments([new Reference('event_sourcing.dbal_connection')]);

        $container->register(MigrationSchemaProvider::class)
            ->setArguments([new Reference(Store::class)]);

        $container->register('event_sourcing.migration.dependency_factory', DependencyFactory::class)
            ->setFactory([DependencyFactory::class, 'fromConnection'])
            ->setArguments([
                new Reference('event_sourcing.migration.configuration'),
                new Reference('event_sourcing.migration.connection'),
            ])
            ->addMethodCall('setService', [
                SchemaProvider::class,
                new Reference(MigrationSchemaProvider::class),
            ]);

        $container->register('event_sourcing.command.migration_diff', DiffCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:diff']);

        $container->register('event_sourcing.command.migration_migrate', MigrateCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:migrate']);

        $container->register('event_sourcing.command.migration_current', CurrentCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:current']);

        $container->register('event_sourcing.command.migration_execute', ExecuteCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:execute']);

        $container->register('event_sourcing.command.migration_status', StatusCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:status']);
    }

    private function configureProfiler(ContainerBuilder $container): void
    {
        $container->register(EventListener::class)
            ->addTag('event_sourcing.processor')
            ->addTag('kernel.reset', ['method' => 'clear']);

        $container->register(EventCollector::class)
            ->setArguments([
                new Reference(EventListener::class),
                '%event_sourcing.aggregates%'
            ])
            ->addTag('data_collector');
    }

    /**
     * @param array<string, array{class: string, snapshot_store: ?string}> $aggregateDefinition
     *
     * @return array<string, string>
     */
    private function aggregateHashMap(array $aggregateDefinition): array
    {
        $result = [];

        foreach ($aggregateDefinition as $name => $definition) {
            $result[$definition['class']] = $name;
        }

        return $result;
    }
}
