<?php

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
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
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\SnapshotRepository;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\WatchServer\DefaultWatchServer;
use Patchlevel\EventSourcing\WatchServer\DefaultWatchServerClient;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use Patchlevel\EventSourcingBundle\DependencyInjection\PatchlevelEventSourcingExtension;
use Patchlevel\EventSourcingBundle\PatchlevelEventSourcingBundle;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor1;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor2;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Profile;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\SnapshotableProfile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Messenger\MessageBusInterface;

class PatchlevelEventSourcingBundleTest extends TestCase
{
    use ProphecyTrait;

    public function testEmptyConfig()
    {
        $container = new ContainerBuilder();
        $bundle = new PatchlevelEventSourcingBundle();

        $bundle->build($container);

        $extension = new PatchlevelEventSourcingExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertFalse($container->has(Store::class));
    }

    public function testMinimalConfig()
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
        self::assertInstanceOf(MultiTableStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertInstanceOf(DefaultProjectionRepository::class, $container->get(ProjectionRepository::class));
    }

    public function testConnectionService()
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
        self::assertInstanceOf(MultiTableStore::class, $container->get(Store::class));
    }

    public function testSingleTable()
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
                        'type' => 'single_table',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(SingleTableStore::class, $container->get(Store::class));
    }

    public function testMultiTable()
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
                        'type' => 'multi_table',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(MultiTableStore::class, $container->get(Store::class));
    }

    public function testOverrideSchemaManager()
    {
        $schemaManager = $this->prophesize(SchemaManager::class)->reveal();

        $container = new ContainerBuilder();
        $container->set('my_schema_manager', $schemaManager);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'store' => [
                        'schema_manager' => 'my_schema_manager',
                    ],
                ],
            ]
        );

        self::assertEquals($schemaManager, $container->get(SchemaManager::class));
    }

    public function testOverrideEventBus()
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
                ],
            ]
        );

        self::assertEquals($eventBus, $container->get(EventBus::class));
    }

    public function testProcessorListener()
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
                'Patchlevel\EventSourcing\Projection\ProjectionListener' => [
                    ['priority' => -32],
                ],
                'Patchlevel\EventSourcingBundle\DataCollector\EventListener' => [
                    []
                ]
            ],
            $container->findTaggedServiceIds('event_sourcing.processor')
        );
    }

    public function testSymfonyEventBus()
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
                    'event_bus' => [
                        'service' => 'event.bus',
                    ],
                ],
            ]
        );

        self::assertInstanceOf(SymfonyEventBus::class, $container->get(EventBus::class));
        self::assertEquals(
            [
                'Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor1' => [
                    ['bus' => 'event.bus', 'priority' => -64],
                ],
                'Patchlevel\EventSourcingBundle\Tests\Fixtures\Processor2' => [
                    ['bus' => 'event.bus', 'priority' => 0],
                ],
                'Patchlevel\EventSourcing\Projection\ProjectionListener' => [
                    ['bus' => 'event.bus', 'priority' => -32],
                ],
                'Patchlevel\EventSourcingBundle\DataCollector\EventListener' => [
                    ['bus' => 'event.bus', 'priority' => 0],
                ]
            ],
            $container->findTaggedServiceIds('messenger.message_handler')
        );
    }

    public function testPsr6SnapshotStore()
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

        self::assertInstanceOf(Psr6SnapshotStore::class, $container->get('event_sourcing.snapshot_store.default'));
    }

    public function testPsr16SnapshotStore()
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

        self::assertInstanceOf(Psr16SnapshotStore::class, $container->get('event_sourcing.snapshot_store.default'));
    }

    public function testCustomSnapshotStore()
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

        self::assertEquals($customSnapshotStore, $container->get('event_sourcing.snapshot_store.default'));
    }

    public function testWatchServer()
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

        self::assertInstanceOf(DefaultWatchServer::class, $container->get(WatchServer::class));
        self::assertInstanceOf(DefaultWatchServerClient::class, $container->get(WatchServerClient::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
    }

    public function testWatchServerWithSymfonyEventBus()
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

        self::assertInstanceOf(DefaultWatchServer::class, $container->get(WatchServer::class));
        self::assertInstanceOf(DefaultWatchServerClient::class, $container->get(WatchServerClient::class));
    }

    public function testDefaultRepository()
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'aggregates' => [
                        'profile' => [
                            'class' => Profile::class,
                        ],
                    ],
                ],
            ]
        );

        self::assertInstanceOf(DefaultRepository::class, $container->get('event_sourcing.repository.profile'));
    }

    public function testSnapshotRepository()
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'aggregates' => [
                        'profile' => [
                            'class' => SnapshotableProfile::class,
                            'snapshot_store' => 'default',
                        ],
                    ],
                    'snapshot_stores' => [
                        'default' => [
                            'service' => 'cache.default',
                        ],
                    ],
                ],
            ]
        );

        self::assertInstanceOf(SnapshotRepository::class, $container->get('event_sourcing.repository.profile'));
    }

    public function testCommands()
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
        self::assertInstanceOf(SchemaCreateCommand::class, $container->get(SchemaCreateCommand::class));
        self::assertInstanceOf(SchemaUpdateCommand::class, $container->get(SchemaUpdateCommand::class));
        self::assertInstanceOf(SchemaDropCommand::class, $container->get(SchemaDropCommand::class));
        self::assertInstanceOf(ProjectionCreateCommand::class, $container->get(ProjectionCreateCommand::class));
        self::assertInstanceOf(ProjectionDropCommand::class, $container->get(ProjectionDropCommand::class));
        self::assertInstanceOf(ProjectionRebuildCommand::class, $container->get(ProjectionRebuildCommand::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
    }

    public function testMigrations()
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

    public function testFullBuild()
    {
        $container = new ContainerBuilder();
        $container->set('my_schema_manager', $this->prophesize(SchemaManager::class)->reveal());

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'service' => 'doctrine.dbal.eventstore_connection',
                    ],
                    'store' => [
                        'type' => 'multi_table',
                        'schema_manager' => 'my_schema_manager',
                    ],
                    'event_bus' => [
                        'type' => 'symfony',
                        'service' => 'event.bus',
                    ],
                    'aggregates' => [
                        'profile' => [
                            'class' => SnapshotableProfile::class,
                            'snapshot_store' => 'default',
                        ],
                    ],
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
                ],
            ]
        );

        self::assertInstanceOf(MultiTableStore::class, $container->get(Store::class));
        self::assertInstanceOf(Repository::class, $container->get('event_sourcing.repository.profile'));
    }

    private function compileContainer(ContainerBuilder $container, array $config): void
    {
        $bundle = new PatchlevelEventSourcingBundle();
        $bundle->build($container);

        $container->setParameter('kernel.project_dir', __DIR__);

        $container->set('doctrine.dbal.eventstore_connection', $this->prophesize(Connection::class)->reveal());
        $container->set('event.bus', $this->prophesize(MessageBusInterface::class)->reveal());
        $container->set('cache.default', $this->prophesize(CacheItemPoolInterface::class)->reveal());

        $extension = new PatchlevelEventSourcingExtension();
        $extension->load($config, $container);

        $compilerPassConfig = $container->getCompilerPassConfig();
        $compilerPassConfig->setRemovingPasses([]);
        $compilerPassConfig->addPass(new TestCaseAllPublicCompilerPass());

        $container->compile();
    }
}
