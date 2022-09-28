<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use DateTimeImmutable;
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
use Patchlevel\EventSourcing\Clock\Clock;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionCreateCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionistBootCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionistRemoveCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionistRunCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionistStatusCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionistTeardownCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\RecordedOnDecorator;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Projection\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\DefaultProjectorRepository;
use Patchlevel\EventSourcing\Projection\MetadataAwareProjectionHandler;
use Patchlevel\EventSourcing\Projection\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Patchlevel\EventSourcing\Projection\Projectionist;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Projection\Projector;
use Patchlevel\EventSourcing\Projection\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\ProjectorResolver;
use Patchlevel\EventSourcing\Projection\ProjectorStore\DatabaseStore;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\Hydrator\EventHydrator;
use Patchlevel\EventSourcing\Serializer\Hydrator\MetadataEventHydrator;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\WatchServer\MessageSerializer;
use Patchlevel\EventSourcing\WatchServer\PhpNativeMessageSerializer;
use Patchlevel\EventSourcing\WatchServer\SocketWatchServer;
use Patchlevel\EventSourcing\WatchServer\SocketWatchServerClient;
use Patchlevel\EventSourcing\WatchServer\WatchListener;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use Patchlevel\EventSourcingBundle\DataCollector\EventSourcingCollector;
use Patchlevel\EventSourcingBundle\DataCollector\MessageListener;
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
         * @var array{event_bus: ?array{type: string, service: string}, projectionist: array{enabled: bool}, watch_server: array{enabled: bool, host: string}, connection: ?array{service: ?string, url: ?string}, store: array{schema_manager: string, type: string, options: array<string, mixed>}, aggregates: list<string>, events: list<string>, snapshot_stores: array<string, array{type: string, service: string}>, migration: array{path: string, namespace: string}, clock: array{freeze: ?string}} $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        if (!isset($config['connection'])) {
            return;
        }

        $this->configureUpcaster($container);
        $this->configureSerializer($config, $container);
        $this->configureMessageDecorator($container);
        $this->configureEventBus($config, $container);
        $this->configureConnection($config, $container);
        $this->configureStorage($config, $container);
        $this->configureSnapshots($config, $container);
        $this->configureAggregates($config, $container);
        $this->configureCommands($container);
        $this->configureProfiler($container);
        $this->configureClock($config, $container);
        $this->configureSchema($config, $container);

        if ($config['projectionist']['enabled']) {
            $this->configureProjectionist($container);
        } else {
            $this->configureProjection($container);
        }

        if (class_exists(DependencyFactory::class)) {
            $this->configureMigration($config, $container);
        }

        if (!$config['watch_server']['enabled']) {
            return;
        }

        $this->configureWatchServer($config, $container);
    }

    /**
     * @param array{events: array<string>} $config
     */
    private function configureSerializer(array $config, ContainerBuilder $container): void
    {
        $container->register(AttributeEventRegistryFactory::class);

        $container->register(EventRegistry::class)
            ->setFactory([new Reference(AttributeEventRegistryFactory::class), 'create'])
            ->setArguments([$config['events']]);

        $container->register(AttributeEventMetadataFactory::class);
        $container->setAlias(EventMetadataFactory::class, AttributeEventMetadataFactory::class);

        $container->register(MetadataEventHydrator::class)
            ->setArguments([new Reference(EventMetadataFactory::class)]);
        $container->setAlias(EventHydrator::class, MetadataEventHydrator::class);

        $container->register(JsonEncoder::class);
        $container->setAlias(Encoder::class, JsonEncoder::class);

        $container->register(DefaultEventSerializer::class)
            ->setArguments([
                new Reference(EventRegistry::class),
                new Reference(EventHydrator::class),
                new Reference(Encoder::class),
                new Reference(Upcaster::class),
            ]);

        $container->setAlias(EventSerializer::class, DefaultEventSerializer::class);
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
            ->setArguments([new Reference(ProjectionHandler::class)])
            ->addTag('event_sourcing.processor', ['priority' => -32]);

        $container->registerForAutoconfiguration(Projection::class)
            ->addTag('event_sourcing.projection');

        $container->register(MetadataAwareProjectionHandler::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.projection')]);

        $container->setAlias(ProjectionHandler::class, MetadataAwareProjectionHandler::class);

        $container->register(ProjectionCreateCommand::class)
            ->setArguments([
                new Reference(ProjectionHandler::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionDropCommand::class)
            ->setArguments([
                new Reference(ProjectionHandler::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionRebuildCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(ProjectionHandler::class),
            ])
            ->addTag('console.command');
    }

    private function configureProjectionist(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(Projector::class)
            ->addTag('event_sourcing.projector');

        $container->register(DefaultProjectorRepository::class)
            ->setArguments([
                new TaggedIteratorArgument('event_sourcing.projector'),
            ]);

        $container->setAlias(ProjectorRepository::class, DefaultProjectorRepository::class);

        $container->register(MetadataProjectorResolver::class);
        $container->setAlias(ProjectorResolver::class, MetadataProjectorResolver::class);

        $container->register(DatabaseStore::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
            ])
            ->addTag('event_sourcing.schema_configurator');

        $container->setAlias(ProjectorStore::class, DatabaseStore::class);

        $container->register(DefaultProjectionist::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(ProjectorStore::class),
                new Reference(ProjectorRepository::class),
                new Reference(ProjectorResolver::class),
            ]);

        $container->setAlias(Projectionist::class, DefaultProjectionist::class);

        $container->register(ProjectionistBootCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionistRunCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionistTeardownCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionistRemoveCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionistStatusCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');
    }

    private function configureUpcaster(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(Upcaster::class)
            ->addTag('event_sourcing.upcaster');

        $container->register(UpcasterChain::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.upcaster')]);

        $container->setAlias(Upcaster::class, UpcasterChain::class);
    }

    private function configureMessageDecorator(ContainerBuilder $container): void
    {
        $container->register(RecordedOnDecorator::class)
            ->setArguments([new Reference(Clock::class)])
            ->addTag('event_sourcing.message_decorator');

        $container->registerForAutoconfiguration(MessageDecorator::class)
            ->addTag('event_sourcing.message_decorator');

        $container->register(ChainMessageDecorator::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.message_decorator')]);

        $container->setAlias(MessageDecorator::class, ChainMessageDecorator::class);
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
        if ($config['store']['type'] === 'single_table') {
            $container->register(SingleTableStore::class)
                ->setArguments([
                    new Reference('event_sourcing.dbal_connection'),
                    new Reference(EventSerializer::class),
                    new Reference(AggregateRootRegistry::class),
                    $config['store']['options']['table_name'] ?? 'eventstore',
                ])
                ->addTag('event_sourcing.schema_configurator');

            $container->setAlias(Store::class, SingleTableStore::class);

            return;
        }

        if ($config['store']['type'] !== 'multi_table') {
            return;
        }

        $container->register(MultiTableStore::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
                new Reference(EventSerializer::class),
                new Reference(AggregateRootRegistry::class),
                $config['store']['options']['table_name'] ?? 'eventstore',
            ])
            ->addTag('event_sourcing.schema_configurator');

        $container->setAlias(Store::class, MultiTableStore::class);
    }

    /**
     * @param array{snapshot_stores: array<string, array{type: string, service: string}>} $config
     */
    private function configureSnapshots(array $config, ContainerBuilder $container): void
    {
        $adapters = [];

        foreach ($config['snapshot_stores'] as $name => $definition) {
            $adapterId = sprintf('event_sourcing.snapshot_store.adapter.%s', $name);
            $adapters[$name] = new Reference($adapterId);

            if ($definition['type'] === 'psr6') {
                $container->register($adapterId, Psr6SnapshotAdapter::class)
                    ->setArguments([new Reference($definition['service'])]);
            }

            if ($definition['type'] === 'psr16') {
                $container->register($adapterId, Psr16SnapshotAdapter::class)
                    ->setArguments([new Reference($definition['service'])]);
            }

            if ($definition['type'] !== 'custom') {
                continue;
            }

            $container->setAlias($adapterId, $definition['service']);
        }

        $container->register(DefaultSnapshotStore::class)
            ->setArguments([$adapters]);

        $container->setAlias(SnapshotStore::class, DefaultSnapshotStore::class);
    }

    /**
     * @param array{aggregates: list<string>} $config
     */
    private function configureAggregates(array $config, ContainerBuilder $container): void
    {
        $container->register(AttributeAggregateRootRegistryFactory::class);

        $container->register(AggregateRootRegistry::class)
            ->setFactory([new Reference(AttributeAggregateRootRegistryFactory::class), 'create'])
            ->setArguments([$config['aggregates']]);

        $container->register(DefaultRepositoryManager::class)
            ->setArguments([
                new Reference(AggregateRootRegistry::class),
                new Reference(Store::class),
                new Reference(EventBus::class),
                new Reference(SnapshotStore::class),
                new Reference(MessageDecorator::class),
            ]);

        $container->setAlias(RepositoryManager::class, DefaultRepositoryManager::class);
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
                new Reference(SchemaDirector::class),
            ])
            ->addTag('console.command');

        $container->register(SchemaUpdateCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(SchemaDirector::class),
            ])
            ->addTag('console.command');

        $container->register(SchemaDropCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(SchemaDirector::class),
            ])
            ->addTag('console.command');

        $container->register(ShowCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(EventSerializer::class),
                new Reference(AggregateRootRegistry::class),
            ])
            ->addTag('console.command');

        $container->register(DebugCommand::class)
            ->setArguments([
                new Reference(AggregateRootRegistry::class),
                new Reference(EventRegistry::class),
            ])
            ->addTag('console.command');
    }

    /**
     * @param array{watch_server: array{host: string}} $config
     */
    private function configureWatchServer(array $config, ContainerBuilder $container): void
    {
        $container->register(PhpNativeMessageSerializer::class)
            ->setArguments([new Reference(EventSerializer::class)]);

        $container->setAlias(MessageSerializer::class, PhpNativeMessageSerializer::class);

        $container->register(SocketWatchServerClient::class)
            ->setArguments([$config['watch_server']['host'], new Reference(MessageSerializer::class)]);

        $container->setAlias(WatchServerClient::class, SocketWatchServerClient::class);

        $container->register(WatchListener::class)
            ->setArguments([new Reference(WatchServerClient::class)])
            ->addTag('event_sourcing.processor');

        $container->register(SocketWatchServer::class)
            ->setArguments([$config['watch_server']['host'], new Reference(MessageSerializer::class)]);

        $container->setAlias(WatchServer::class, SocketWatchServer::class);

        $container->register(WatchCommand::class)
            ->setArguments([
                new Reference(WatchServer::class),
                new Reference(EventSerializer::class),
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

        $container->register(DoctrineMigrationSchemaProvider::class)
            ->setArguments([new Reference(DoctrineSchemaProvider::class)]);

        $container->register('event_sourcing.migration.dependency_factory', DependencyFactory::class)
            ->setFactory([DependencyFactory::class, 'fromConnection'])
            ->setArguments([
                new Reference('event_sourcing.migration.configuration'),
                new Reference('event_sourcing.migration.connection'),
            ])
            ->addMethodCall('setService', [
                SchemaProvider::class,
                new Reference(DoctrineMigrationSchemaProvider::class),
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
        $container->register(MessageListener::class)
            ->addTag('event_sourcing.processor')
            ->addTag('kernel.reset', ['method' => 'clear']);

        $container->register(EventSourcingCollector::class)
            ->setArguments([
                new Reference(MessageListener::class),
                new Reference(AggregateRootRegistry::class),
                new Reference(EventRegistry::class),
                new Reference(EventSerializer::class),
            ])
            ->addTag('data_collector', ['template' => '@PatchlevelEventSourcing/Collector/template.html.twig']);
    }

    /**
     * @param array{clock: array{freeze: ?string}} $config
     */
    private function configureClock(array $config, ContainerBuilder $container): void
    {
        if ($config['clock']['freeze'] === null) {
            $container->register(SystemClock::class);
            $container->setAlias(Clock::class, SystemClock::class);

            return;
        }

        $container->register(FrozenClock::class)
            ->setArguments([new DateTimeImmutable($config['clock']['freeze'])]);

        $container->setAlias(Clock::class, FrozenClock::class);
    }

    /**
     * @param array{store: array{schema_manager: string}} $config
     */
    private function configureSchema(array $config, ContainerBuilder $container): void
    {
        $container->register(ChainSchemaConfigurator::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.schema_configurator')]);

        $container->setAlias(SchemaConfigurator::class, ChainSchemaConfigurator::class);

        $container->register(DoctrineSchemaDirector::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
                new Reference(SchemaConfigurator::class),
            ]);

        $container->setAlias(DoctrineSchemaProvider::class, DoctrineSchemaDirector::class);
        $container->setAlias(SchemaDirector::class, DoctrineSchemaDirector::class);

        // deprecated
        if ($config['store']['schema_manager']) {
            $container->setAlias(SchemaManager::class, $config['store']['schema_manager']);
        } else {
            $container->register(DoctrineSchemaManager::class);
            $container->setAlias(SchemaManager::class, DoctrineSchemaManager::class);
        }
    }
}
