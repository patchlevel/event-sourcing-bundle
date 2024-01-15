<?php

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
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
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Psr14EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Projection\Projectionist\SyncProjectionistEventBusWrapper;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaSubscriber;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\WatchServer\SocketWatchServer;
use Patchlevel\EventSourcing\WatchServer\SocketWatchServerClient;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use Patchlevel\EventSourcingBundle\DependencyInjection\PatchlevelEventSourcingExtension;
use Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcingBundle\Listener\ProjectionistAutoBootListener;
use Patchlevel\EventSourcingBundle\Listener\ProjectionistAutoRecoveryListener;
use Patchlevel\EventSourcingBundle\Listener\ProjectionistAutoTeardownListener;
use Patchlevel\EventSourcingBundle\PatchlevelEventSourcingBundle;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\CreatedSubscriber;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor1;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor2;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Profile;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileCreated;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileProjector;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\SnapshotableProfile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

class PatchlevelEventSourcingBundleTest extends TestCase
{
    use ProphecyTrait;

    public function testEmptyConfig(): void
    {
        $container = new ContainerBuilder();
        $bundle = new PatchlevelEventSourcingBundle();

        $bundle->build($container);

        $extension = new PatchlevelEventSourcingExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertFalse($container->has(Store::class));
    }

    public function testMinimalConfig(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'url' => 'sqlite:///:memory:',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(Connection::class, $container->get('event_sourcing.dbal_connection'));
        self::assertInstanceOf(DoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(SyncProjectionistEventBusWrapper::class, $container->get(EventBus::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(SystemClock::class, $container->get('event_sourcing.clock'));
    }

    public function testConnectionService(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(Connection::class, $container->get('event_sourcing.dbal_connection'));
        self::assertInstanceOf(DoctrineDbalStore::class, $container->get(Store::class));
    }

    public function testSymfonyEventBus(): void
    {
        $eventBus = $this->prophesize(MessageBusInterface::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('my_event_bus', $eventBus);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'event_bus' => [
                        'type' => 'symfony',
                        'service' => 'my_event_bus',
                    ],
                    'projection' => [
                        'sync' => false,
                    ],
                ],
            ]
        );

        self::assertEquals(new SymfonyEventBus($eventBus), $container->get(EventBus::class));
    }

    public function testPsr14EventBus(): void
    {
        $eventBus = $this->prophesize(EventDispatcherInterface::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('my_event_bus', $eventBus);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'event_bus' => [
                        'type' => 'psr14',
                        'service' => 'my_event_bus',
                    ],
                    'projection' => [
                        'sync' => false,
                    ],
                ],
            ]
        );

        self::assertEquals(new Psr14EventBus($eventBus), $container->get(EventBus::class));
    }

    public function testCustomEventBus(): void
    {
        $eventBus = $this->prophesize(EventBus::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('my_event_bus', $eventBus);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'event_bus' => [
                        'type' => 'custom',
                        'service' => 'my_event_bus',
                    ],
                    'projection' => [
                        'sync' => false,
                    ],
                ],
            ]
        );

        self::assertEquals($eventBus, $container->get(EventBus::class));
    }

    public function testProcessorListener(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(Processor1::class, new Definition(Processor1::class))
            ->addTag('event_sourcing.processor', ['priority' => -64]);
        $container->setDefinition(Processor2::class, new Definition(Processor2::class))
            ->addTag('event_sourcing.processor');

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'projection' => [
                        'sync' => false,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertEquals(
            [
                'Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor1' => [
                    ['priority' => -64],
                ],
                'Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor2' => [
                    [],
                ],
                'Patchlevel\EventSourcingBundle\DataCollector\MessageListener' => [
                    []
                ]
            ],
            $container->findTaggedServiceIds('event_sourcing.processor')
        );
    }


    public function testAutoconfigureProcessorListener(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(Processor1::class, new Definition(Processor1::class))
            ->setAutoconfigured(true);
        $container->setDefinition(Processor2::class, new Definition(Processor1::class))
            ->setAutoconfigured(false);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'projection' => [
                        'sync' => false,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertEquals(
            [
                'Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor1' => [
                    [
                        'priority' => 0,
                    ],
                ],
                'Patchlevel\EventSourcingBundle\DataCollector\MessageListener' => [
                    []
                ]
            ],
            $container->findTaggedServiceIds('event_sourcing.processor')
        );
    }

    public function testSnapshotStore(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'snapshot_stores' => [
                        'default' => [
                            'service' => 'cache.default',
                        ],
                    ],
                ],
            ]
        );

        $snapshotStore = $container->get(SnapshotStore::class);

        self::assertInstanceOf(DefaultSnapshotStore::class, $snapshotStore);

        $adapter = $snapshotStore->adapter(SnapshotableProfile::class);

        self::assertInstanceOf(Psr6SnapshotAdapter::class, $adapter);
    }

    public function testPsr6SnapshotAdapter(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'snapshot_stores' => [
                        'default' => [
                            'service' => 'cache.default',
                        ],
                    ],
                ],
            ]
        );

        self::assertInstanceOf(Psr6SnapshotAdapter::class, $container->get('event_sourcing.snapshot_store.adapter.default'));
    }

    public function testPsr16SnapshotAdapter(): void
    {
        $simpleCache = $this->prophesize(CacheInterface::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('simple_cache', $simpleCache);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'snapshot_stores' => [
                        'default' => [
                            'type' => 'psr16',
                            'service' => 'simple_cache',
                        ],
                    ],
                ],
            ]
        );

        self::assertInstanceOf(Psr16SnapshotAdapter::class, $container->get('event_sourcing.snapshot_store.adapter.default'));
    }

    public function testCustomSnapshotAdapter(): void
    {
        $customSnapshotStore = $this->prophesize(SnapshotStore::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('my_snapshot_store', $customSnapshotStore);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'snapshot_stores' => [
                        'default' => [
                            'type' => 'custom',
                            'service' => 'my_snapshot_store',
                        ],
                    ],
                ],
            ]
        );

        self::assertEquals($customSnapshotStore, $container->get('event_sourcing.snapshot_store.adapter.default'));
    }

    public function testWatchServer(): void
    {
        $customSnapshotStore = $this->prophesize(SnapshotStore::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('my_snapshot_store', $customSnapshotStore);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'watch_server' => [
                        'enabled' => true,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(SocketWatchServer::class, $container->get(WatchServer::class));
        self::assertInstanceOf(SocketWatchServerClient::class, $container->get(WatchServerClient::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
    }

    public function testWatchServerWithSymfonyEventBus(): void
    {
        $customSnapshotStore = $this->prophesize(SnapshotStore::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('my_snapshot_store', $customSnapshotStore);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'event_bus' => [
                        'service' => 'event.bus',
                    ],
                    'watch_server' => [
                        'enabled' => true,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(SocketWatchServer::class, $container->get(WatchServer::class));
        self::assertInstanceOf(SocketWatchServerClient::class, $container->get(WatchServerClient::class));
    }

    public function testEventRegistry(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'events' => [__DIR__ . '/../Fixtures'],
                ],
            ]
        );

        $eventRegistry = $container->get(EventRegistry::class);

        self::assertInstanceOf(EventRegistry::class, $eventRegistry);
        self::assertTrue($eventRegistry->hasEventClass(ProfileCreated::class));
    }

    public function testAggregateRegistry(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                ],
            ]
        );

        $aggregateRegistry = $container->get(AggregateRootRegistry::class);

        self::assertInstanceOf(AggregateRootRegistry::class, $aggregateRegistry);
        self::assertTrue($aggregateRegistry->hasAggregateClass(Profile::class));
    }

    public function testRepositoryManager(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                ],
            ]
        );

        $repositoryManager = $container->get(RepositoryManager::class);

        self::assertInstanceOf(RepositoryManager::class, $repositoryManager);

        $repository = $repositoryManager->get(Profile::class);

        self::assertInstanceOf(DefaultRepository::class, $repository);
    }

    public function testCommands(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(DatabaseCreateCommand::class, $container->get(DatabaseCreateCommand::class));
        self::assertInstanceOf(DatabaseDropCommand::class, $container->get(DatabaseDropCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ProjectionBootCommand::class, $container->get(ProjectionBootCommand::class));
        self::assertInstanceOf(ProjectionReactivateCommand::class, $container->get(ProjectionReactivateCommand::class));
        self::assertInstanceOf(ProjectionRebuildCommand::class, $container->get(ProjectionRebuildCommand::class));
        self::assertInstanceOf(ProjectionRemoveCommand::class, $container->get(ProjectionRemoveCommand::class));
        self::assertInstanceOf(ProjectionRunCommand::class, $container->get(ProjectionRunCommand::class));
        self::assertInstanceOf(ProjectionStatusCommand::class, $container->get(ProjectionStatusCommand::class));
        self::assertInstanceOf(ProjectionTeardownCommand::class, $container->get(ProjectionTeardownCommand::class));
        self::assertInstanceOf(SchemaCreateCommand::class, $container->get(SchemaCreateCommand::class));
        self::assertInstanceOf(SchemaUpdateCommand::class, $container->get(SchemaUpdateCommand::class));
        self::assertInstanceOf(SchemaDropCommand::class, $container->get(SchemaDropCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
    }

    public function testMigrations(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(DiffCommand::class, $container->get('event_sourcing.command.migration_diff'));
        self::assertInstanceOf(MigrateCommand::class, $container->get('event_sourcing.command.migration_migrate'));
        self::assertInstanceOf(CurrentCommand::class, $container->get('event_sourcing.command.migration_current'));
        self::assertInstanceOf(ExecuteCommand::class, $container->get('event_sourcing.command.migration_execute'));
        self::assertInstanceOf(StatusCommand::class, $container->get('event_sourcing.command.migration_status'));
    }

    public function testDefaultClock(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(SystemClock::class, $container->get('event_sourcing.clock'));
    }

    public function testFrozenClock(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'clock' => [
                        'freeze' => '2020-01-01 22:00:00',
                    ],
                ],
            ]
        );

        $clock = $container->get('event_sourcing.clock');

        self::assertInstanceOf(FrozenClock::class, $clock);
        self::assertSame('2020-01-01 22:00:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testPsrClock(): void
    {
        $psrClock = $this->prophesize(ClockInterface::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('clock', $psrClock);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'clock' => [
                        'service' => 'clock',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(ClockInterface::class, $container->get('event_sourcing.clock'));
    }

    public function testDecorator(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
    }

    public function testProjectionistSync(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'projection' => [
                        'sync' => true,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(SyncProjectionistEventBusWrapper::class, $container->get(EventBus::class));
        self::assertInstanceOf(DefaultProjectionist::class, $container->get(Projectionist::class));
    }

    public function testProjectionistAsync(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'projection' => [
                        'sync' => false,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
    }

    public function testProjectionistAutoBoot(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'projection' => [
                        'auto_boot' => true,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(ProjectionistAutoBootListener::class, $container->get(ProjectionistAutoBootListener::class));
    }

    public function testProjectionistAutoRecovery(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'projection' => [
                        'auto_recovery' => true,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(ProjectionistAutoRecoveryListener::class, $container->get(ProjectionistAutoRecoveryListener::class));
    }

    public function testProjectionistAutoTeardown(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'projection' => [
                        'auto_teardown' => true,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(ProjectionistAutoTeardownListener::class, $container->get(ProjectionistAutoTeardownListener::class));
    }

    public function testAutoconfigureProjector(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ProfileProjector::class, new Definition(ProfileProjector::class))
            ->setAutoconfigured(true);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                ],
            ]
        );

        $repository = $container->get(ProjectorRepository::class);
        $projectors = $repository->projectors();

        self::assertCount(1, $projectors);
        self::assertInstanceOf(ProfileProjector::class, $projectors[0]);
    }

    public function testSchemaMerge(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'store' => [
                        'merge_orm_schema' => true,
                    ],
                    'projection' => [
                        'sync' => false,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(DoctrineSchemaSubscriber::class, $container->get(DoctrineSchemaSubscriber::class));
        self::assertFalse($container->has(SchemaDirector::class));
        self::assertFalse($container->has(DoctrineSchemaProvider::class));
        self::assertFalse($container->has(DatabaseCreateCommand::class));
        self::assertFalse($container->has('event_sourcing.command.migration_diff'));
    }

    public function testFullBuild(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'store' => [
                    ],
                    'event_bus' => [
                        'type' => 'default',
                    ],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                    'migration' => [
                        'namespace' => 'Foo',
                        'path' => 'src',
                    ],
                    'snapshot_stores' => [
                        'default' => [
                            'type' => 'psr6',
                            'service' => 'cache.default',
                        ],
                    ],
                    'watch_server' => [
                        'enabled' => true,
                        'host' => 'localhost',
                    ],
                    'projection' => [
                        'sync' => false,
                    ],
                ],
            ]
        );

        self::assertInstanceOf(Connection::class, $container->get('event_sourcing.dbal_connection'));
        self::assertInstanceOf(DoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(RepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
    }

    private function compileContainer(ContainerBuilder $container, array $config): void
    {
        $bundle = new PatchlevelEventSourcingBundle();
        $bundle->build($container);

        $container->setParameter('kernel.project_dir', __DIR__);

        $container->set('doctrine.dbal.eventstore_connection', $this->prophesize(Connection::class)->reveal());
        $container->set('event.bus', $this->prophesize(MessageBusInterface::class)->reveal());
        $container->set('cache.default', $this->prophesize(CacheItemPoolInterface::class)->reveal());
        $container->set('lock.default.factory', $this->prophesize(LockFactory::class)->reveal());
        $container->set('event_dispatcher', $this->prophesize(EventDispatcherInterface::class)->reveal());
        $container->set(LoggerInterface::class, $this->prophesize(LoggerInterface::class)->reveal());

        $extension = new PatchlevelEventSourcingExtension();
        $extension->load($config, $container);

        $compilerPassConfig = $container->getCompilerPassConfig();
        $compilerPassConfig->setRemovingPasses([]);
        $compilerPassConfig->addPass(new TestCaseAllPublicCompilerPass());

        $container->compile();
    }
}
