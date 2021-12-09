<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

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
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Schema\MigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\WatchServer\WatchListener;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
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
         * @var array{message_bus: string, watch_server: array{enabled: bool, host: string}, store: array{schema_manager: string, dbal_connection: string, type: string, options: array<string, mixed>}, aggregates: array<string, string>, migration: array{path: string, namespace: string}} $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        $this->configureEventBus($config, $container);
        $this->configureProjection($config, $container);
        $this->configureStorage($config, $container);
        $this->configureAggregates($config, $container);
        $this->configureCommands($config, $container);

        if (class_exists(DependencyFactory::class)) {
            $this->configureMigration($config, $container);
        }

        if (!$config['watch_server']['enabled']) {
            return;
        }

        $this->configureWatchServer($config, $container);
    }

    /**
     * @param array{message_bus: ?string} $config
     */
    private function configureEventBus(array $config, ContainerBuilder $container): void
    {
        if ($config['message_bus']) {
            $container->register(SymfonyEventBus::class)
                ->setArguments([new Reference($config['message_bus'])]);

            $container->setAlias(EventBus::class, SymfonyEventBus::class);

            $container->registerForAutoconfiguration(Listener::class)
                ->addTag('messenger.message_handler', ['bus' => $config['message_bus']]);

            return;
        }

        $container->register(DefaultEventBus::class);
        $container->setAlias(EventBus::class, DefaultEventBus::class);

        $container->registerForAutoconfiguration(Listener::class)
            ->addTag('event_sourcing.event_listener');
    }

    /**
     * @param array{message_bus: ?string} $config
     */
    private function configureProjection(array $config, ContainerBuilder $container): void
    {
        $projectionListener = $container->register(ProjectionListener::class)
            ->setArguments([new Reference(ProjectionRepository::class)]);

        if ($config['message_bus']) {
            $projectionListener->addTag('messenger.message_handler', ['bus' => $config['message_bus']]);
        } else {
            $projectionListener->addTag('event_sourcing.event_listener');
        }

        $container->registerForAutoconfiguration(Projection::class)
            ->addTag('event_sourcing.projection');

        $container->register(DefaultProjectionRepository::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.projection')]);

        $container->setAlias(ProjectionRepository::class, DefaultProjectionRepository::class);
    }

    /**
     * @param array{store: array{schema_manager: string, dbal_connection: string, type: string, options: array<string, mixed>}, aggregates: array<string, string>} $config
     */
    private function configureStorage(array $config, ContainerBuilder $container): void
    {
        if ($config['store']['schema_manager']) {
            $container->setAlias(SchemaManager::class, $config['store']['schema_manager']);
        } else {
            $container->register(DoctrineSchemaManager::class);
            $container->setAlias(SchemaManager::class, DoctrineSchemaManager::class);
        }

        $dbalConnectionId = sprintf('doctrine.dbal.%s_connection', $config['store']['dbal_connection']);

        if ($config['store']['type'] === 'dbal_single_table') {
            $container->register(SingleTableStore::class)
                ->setArguments([
                    new Reference($dbalConnectionId),
                    $config['aggregates'],
                    $config['store']['options']['table_name'] ?? 'eventstore',
                ]);

            $container->setAlias(Store::class, SingleTableStore::class)
                ->setPublic(true);

            return;
        }

        if ($config['store']['type'] === 'dbal_multi_table') {
            $container->register(MultiTableStore::class)
                ->setArguments([
                    new Reference($dbalConnectionId),
                    $config['aggregates'],
                ]);

            $container->setAlias(Store::class, MultiTableStore::class)
                ->setPublic(true);

            return;
        }

        $container->setAlias(Store::class, $config['store']['type'])
            ->setPublic(true);
    }

    /**
     * @param array{aggregates: array<string, string>} $config
     */
    private function configureAggregates(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('event_sourcing.aggregates', $config['aggregates']);

        foreach ($config['aggregates'] as $aggregateClass => $aggregateName) {
            $id = sprintf('event_sourcing.%s_repository', $aggregateName);

            $container->register($id, Repository::class)
                ->setArguments([
                    new Reference(Store::class),
                    new Reference(EventBus::class),
                    $aggregateClass,
                ])
                ->setPublic(true);
        }
    }

    /**
     * @param array{aggregates: array<string, string>} $config
     */
    private function configureCommands(array $config, ContainerBuilder $container): void
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
                $config['aggregates'],
            ])
            ->addTag('console.command');
    }

    /**
     * @param array{message_bus: ?string, watch_server: array{host: string}} $config
     */
    private function configureWatchServer(array $config, ContainerBuilder $container): void
    {
        $container->register(WatchServerClient::class)
            ->setArguments([$config['watch_server']['host']]);

        $listener = $container->register(WatchListener::class)
            ->setArguments([new Reference(WatchServerClient::class)]);

        if ($config['message_bus']) {
            $listener->addTag('messenger.message_handler', ['bus' => $config['message_bus']]);
        } else {
            $listener->addTag('event_sourcing.event_listener');
        }

        $container->register(WatchServer::class)
            ->setArguments([$config['watch_server']['host']]);

        $container->register(WatchCommand::class)
            ->setArguments([
                new Reference(WatchServer::class),
            ])
            ->addTag('console.command');
    }

    /**
     * @param array{store: array{dbal_connection: string}, migration: array{path: string, namespace: string}} $config
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
            ->setArguments([new Reference(sprintf('doctrine.dbal.%s_connection', $config['store']['dbal_connection']))]);

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

        $container->register('event_sourcing.command.diff', DiffCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:diff']);

        $container->register('event_sourcing.command.migrate', MigrateCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:migrate']);

        $container->register('event_sourcing.command.current', CurrentCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:current']);

        $container->register('event_sourcing.command.execute', ExecuteCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:execute']);

        $container->register('event_sourcing.command.status', StatusCommand::class)
            ->setArguments([new Reference('event_sourcing.migration.dependency_factory')])
            ->addTag('console.command', ['command' => 'event-sourcing:migration:status']);
    }
}
