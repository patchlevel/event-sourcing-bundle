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
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionBootCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionReactivateCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionRemoveCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionRunCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionStatusCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionTeardownCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowAggregateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\EventBus\Psr14EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\SyncProjectionistEventBusWrapper;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaSubscriber;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\WatchServer\MessageSerializer;
use Patchlevel\EventSourcing\WatchServer\PhpNativeMessageSerializer;
use Patchlevel\EventSourcing\WatchServer\SocketWatchServer;
use Patchlevel\EventSourcing\WatchServer\SocketWatchServerClient;
use Patchlevel\EventSourcing\WatchServer\WatchListener;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use Patchlevel\EventSourcingBundle\Attribute\AsProcessor;
use Patchlevel\EventSourcingBundle\DataCollector\EventSourcingCollector;
use Patchlevel\EventSourcingBundle\DataCollector\MessageListener;
use Patchlevel\EventSourcingBundle\Listener\ProjectionistAutoBootListener;
use Patchlevel\EventSourcingBundle\Listener\ProjectionistAutoRecoveryListener;
use Patchlevel\EventSourcingBundle\Listener\ProjectionistAutoTeardownListener;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

use function class_exists;
use function sprintf;

/**
 * @psalm-type Config = array{
 *     event_bus: array{type: string, service: string},
 *     projection: array{sync: bool, auto_boot: bool, auto_recovery: bool, auto_teardown: bool},
 *     watch_server: array{enabled: bool, host: string},
 *     connection: ?array{service: ?string, url: ?string},
 *     store: array{merge_orm_schema: bool, options: array<string, mixed>},
 *     aggregates: list<string>,
 *     events: list<string>,
 *     snapshot_stores: array<string, array{type: string, service: string}>,
 *     migration: array{path: string, namespace: string},
 *     clock: array{freeze: ?string, service: ?string}
 * }
 */
final class PatchlevelEventSourcingExtension extends Extension
{
    /** @param array<array-key, mixed> $configs */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        /** @var Config $config */
        $config = $this->processConfiguration($configuration, $configs);

        if (!isset($config['connection'])) {
            return;
        }

        $this->configureHydrator($container);
        $this->configureUpcaster($container);
        $this->configureSerializer($config, $container);
        $this->configureMessageDecorator($container);
        $this->configureEventBus($config, $container);
        $this->configureConnection($config, $container);
        $this->configureStore($config, $container);
        $this->configureSnapshots($config, $container);
        $this->configureAggregates($config, $container);
        $this->configureCommands($container);
        $this->configureProfiler($container);
        $this->configureClock($config, $container);
        $this->configureSchema($config, $container);
        $this->configureProjection($config, $container);
        $this->configureWatchServer($config, $container);

        if (!class_exists(DependencyFactory::class) || $config['store']['merge_orm_schema'] !== false) {
            return;
        }

        $this->configureMigration($config, $container);
    }

    /** @param Config $config */
    private function configureSerializer(array $config, ContainerBuilder $container): void
    {
        $container->register(AttributeEventRegistryFactory::class);

        $container->register(EventRegistry::class)
            ->setFactory([new Reference(AttributeEventRegistryFactory::class), 'create'])
            ->setArguments([$config['events']]);

        $container->register(AttributeEventMetadataFactory::class);
        $container->setAlias(EventMetadataFactory::class, AttributeEventMetadataFactory::class);

        $container->register(JsonEncoder::class);
        $container->setAlias(Encoder::class, JsonEncoder::class);

        $container->register(DefaultEventSerializer::class)
            ->setArguments([
                new Reference(EventRegistry::class),
                new Reference(Hydrator::class),
                new Reference(Encoder::class),
                new Reference(Upcaster::class),
            ]);

        $container->setAlias(EventSerializer::class, DefaultEventSerializer::class);
    }

    /** @param Config $config */
    private function configureEventBus(array $config, ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            AsProcessor::class,
            static function (ChildDefinition $definition, AsProcessor $attribute): void {
                $definition->addTag('event_sourcing.processor', [
                    'priority' => $attribute->priority,
                ]);
            },
        );

        if ($config['event_bus']['type'] === 'default') {
            $container->register(AttributeListenerProvider::class)
                ->setArguments([new TaggedIteratorArgument('event_sourcing.processor')]);

            $container->setAlias(ListenerProvider::class, AttributeListenerProvider::class);

            $container
                ->register(DefaultEventBus::class)
                ->setArguments([
                    new Reference(ListenerProvider::class),
                    new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('monolog.logger', ['channel' => 'event_sourcing']);

            $container->setAlias(EventBus::class, DefaultEventBus::class);

            return;
        }

        if ($config['event_bus']['type'] === 'psr14') {
            $container->register(Psr14EventBus::class)
                ->setArguments([new Reference($config['event_bus']['service'])]);

            $container->setAlias(EventBus::class, Psr14EventBus::class);

            return;
        }

        $container->register($config['event_bus']['service'], EventBus::class);
        $container->setAlias(EventBus::class, $config['event_bus']['service']);
    }

    /** @param Config $config */
    private function configureProjection(array $config, ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            Projector::class,
            static function (ChildDefinition $definition): void {
                $definition->addTag('event_sourcing.projector');
            },
        );

        $container->register(InMemoryProjectorRepository::class)
            ->setArguments([
                new TaggedIteratorArgument('event_sourcing.projector'),
            ]);

        $container->setAlias(ProjectorRepository::class, InMemoryProjectorRepository::class);

        $container->register(MetadataProjectorResolver::class);
        $container->setAlias(ProjectorResolver::class, MetadataProjectorResolver::class);

        $container->register(DoctrineStore::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
            ])
            ->addTag('event_sourcing.schema_configurator');

        $container->setAlias(ProjectionStore::class, DoctrineStore::class);

        $container->register(DefaultProjectionist::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(ProjectionStore::class),
                new Reference(ProjectorRepository::class),
                new Reference(ProjectorResolver::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('monolog.logger', ['channel' => 'event_sourcing']);

        $container->setAlias(Projectionist::class, DefaultProjectionist::class);

        if ($config['projection']['auto_boot']) {
            $container->register(ProjectionistAutoBootListener::class)
                ->setArguments([
                    new Reference(Projectionist::class),
                    new Reference('lock.default.factory'),
                ])
                ->addTag('kernel.event_listener', ['priority' => 0]);
        }

        if ($config['projection']['auto_recovery']) {
            $container->register(ProjectionistAutoRecoveryListener::class)
                ->setArguments([
                    new Reference(Projectionist::class),
                    new Reference('lock.default.factory'),
                ])
                ->addTag('kernel.event_listener', ['priority' => 2]);
        }

        if ($config['projection']['auto_teardown']) {
            $container->register(ProjectionistAutoTeardownListener::class)
                ->setArguments([
                    new Reference(Projectionist::class),
                    new Reference('lock.default.factory'),
                ])
                ->addTag('kernel.event_listener', ['priority' => -2]);
        }

        if (!$config['projection']['sync']) {
            return;
        }

        $innerService = $container->getAlias(EventBus::class);

        $container->register(SyncProjectionistEventBusWrapper::class)
            ->setArguments([
                new Reference((string)$innerService),
                new Reference(Projectionist::class),
                new Reference('lock.default.factory'),
            ]);

        $container->setAlias(EventBus::class, SyncProjectionistEventBusWrapper::class);
    }

    private function configureHydrator(ContainerBuilder $container): void
    {
        $container->register(MetadataHydrator::class);

        $container->setAlias(Hydrator::class, MetadataHydrator::class);
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
        $container->register(SplitStreamDecorator::class)
            ->setArguments([new Reference(EventMetadataFactory::class)])
            ->addTag('event_sourcing.message_decorator');

        $container->registerForAutoconfiguration(MessageDecorator::class)
            ->addTag('event_sourcing.message_decorator');

        $container->register(ChainMessageDecorator::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.message_decorator')]);

        $container->setAlias(MessageDecorator::class, ChainMessageDecorator::class);
    }

    /** @param Config $config */
    private function configureConnection(array $config, ContainerBuilder $container): void
    {
        if (!$config['connection']) {
            return;
        }

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

    /** @param Config $config */
    private function configureStore(array $config, ContainerBuilder $container): void
    {
        $container->register(DoctrineDbalStore::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
                new Reference(EventSerializer::class),
                new Reference(AggregateRootRegistry::class),
                $config['store']['options']['table_name'] ?? 'eventstore',
            ])
            ->addTag('event_sourcing.schema_configurator');

        $container->setAlias(Store::class, DoctrineDbalStore::class);
    }

    /** @param Config $config */
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

    /** @param Config $config */
    private function configureAggregates(array $config, ContainerBuilder $container): void
    {
        $container->register(AggregateRootMetadataAwareMetadataFactory::class);
        $container->setAlias(AggregateRootMetadataFactory::class, AggregateRootMetadataAwareMetadataFactory::class);

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
                new Reference('event_sourcing.clock'),
                new Reference(AggregateRootMetadataFactory::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('monolog.logger', ['channel' => 'event_sourcing']);

        $container->setAlias(RepositoryManager::class, DefaultRepositoryManager::class);
    }

    private function configureCommands(ContainerBuilder $container): void
    {
        $container->register(ShowCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(EventSerializer::class),
            ])
            ->addTag('console.command');

        $container->register(ShowAggregateCommand::class)
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

        $container->register(ProjectionBootCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionRunCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionTeardownCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionRemoveCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionStatusCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionReactivateCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');

        $container->register(ProjectionRebuildCommand::class)
            ->setArguments([
                new Reference(Projectionist::class),
            ])
            ->addTag('console.command');
    }

    /** @param Config $config */
    private function configureWatchServer(array $config, ContainerBuilder $container): void
    {
        if (!$config['watch_server']['enabled']) {
            return;
        }

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

    /** @param Config $config */
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

    /** @param Config $config */
    private function configureClock(array $config, ContainerBuilder $container): void
    {
        if ($config['clock']['freeze'] !== null) {
            $container->register(FrozenClock::class)
                ->setArguments([new DateTimeImmutable($config['clock']['freeze'])]);

            $container->setAlias('event_sourcing.clock', FrozenClock::class);

            return;
        }

        if ($config['clock']['service']) {
            $container->setAlias('event_sourcing.clock', $config['clock']['service']);

            return;
        }

        $container->register(SystemClock::class);
        $container->setAlias('event_sourcing.clock', SystemClock::class);
    }

    /** @param Config $config */
    private function configureSchema(array $config, ContainerBuilder $container): void
    {
        $container->register(ChainSchemaConfigurator::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.schema_configurator')]);

        $container->setAlias(SchemaConfigurator::class, ChainSchemaConfigurator::class);

        if ($config['store']['merge_orm_schema']) {
            $container->register(DoctrineSchemaSubscriber::class)
                ->setArguments([new Reference(SchemaConfigurator::class)])
                ->addTag('doctrine.event_subscriber');

            return;
        }

        $container->register(DoctrineSchemaDirector::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
                new Reference(SchemaConfigurator::class),
            ]);

        $container->setAlias(DoctrineSchemaProvider::class, DoctrineSchemaDirector::class);
        $container->setAlias(SchemaDirector::class, DoctrineSchemaDirector::class);

        $container->register(DoctrineHelper::class);

        $container->register(DatabaseCreateCommand::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
                new Reference(DoctrineHelper::class),
            ])
            ->addTag('console.command');

        $container->register(DatabaseDropCommand::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
                new Reference(DoctrineHelper::class),
            ])
            ->addTag('console.command');

        $container->register(SchemaCreateCommand::class)
            ->setArguments([
                new Reference(SchemaDirector::class),
            ])
            ->addTag('console.command');

        $container->register(SchemaUpdateCommand::class)
            ->setArguments([
                new Reference(SchemaDirector::class),
            ])
            ->addTag('console.command');

        $container->register(SchemaDropCommand::class)
            ->setArguments([
                new Reference(SchemaDirector::class),
            ])
            ->addTag('console.command');
    }
}
