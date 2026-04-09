<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use ArrayObject;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Doctrine\Persistence\ManagerRegistry;
use Fixtures\DummyExtension;
use Fixtures\DummyGuesser;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\InstantRetryCommandBus;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowAggregateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Console\Command\StoreMigrateCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionBootCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionPauseCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionReactivateCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRefreshCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRemoveCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRunCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionSetupCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionTeardownCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Psr14EventBus;
use Patchlevel\EventSourcing\Message\Translator\AggregateToStreamHeaderTranslator;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventWithHeaderTranslator;
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaListener;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\ReadOnlyStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\StreamReadOnlyStore;
use Patchlevel\EventSourcing\Store\StreamStore;
use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\DefaultCleaner;
use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcingBundle\DependencyInjection\PatchlevelEventSourcingExtension;
use Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcingBundle\Normalizer\SymfonyExtension;
use Patchlevel\EventSourcingBundle\Normalizer\SymfonyGuesser;
use Patchlevel\EventSourcingBundle\PatchlevelEventSourcingBundle;
use Patchlevel\EventSourcingBundle\QueryBus\SymfonyQueryBus;
use Patchlevel\EventSourcingBundle\Subscription\ResetServicesListener;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\CreateProfile;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\CustomHeader;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\DummyArgumentResolver;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Listener1;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Listener2;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Profile;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileCreated;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileProcessor;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileProjector;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileSubscriber;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\QueryFoo;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\SnapshotableProfile;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\StackHydrator;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\XmlDumper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\MessageBusInterface;

use function sprintf;

final class PatchlevelEventSourcingBundleTest extends TestCase
{
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

    #[RequiresMethod(ContainerBuilder::class, 'getAttributeAutoconfigurators')]
    public function testMinimalConfig(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['url' => 'sqlite3:///:memory:'],
                ],
            ],
        );

        self::assertInstanceOf(Connection::class, $container->get('event_sourcing.dbal_connection'));
        self::assertInstanceOf(DoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(SystemClock::class, $container->get('event_sourcing.clock'));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));

        self::assertFalse($container->has(EventBus::class));

        $attributes = $container->getAttributeAutoconfigurators();
        foreach ([Aggregate::class, Event::class] as $class) {
            $definition = new ChildDefinition('');

            foreach ($attributes[$class] as $attributeCallable) {
                $attributeCallable($definition);
            }

            $this->assertSame(
                [['source' => sprintf('with #[%s] attribute', $class)]],
                $definition->getTag('container.excluded'),
            );
            $this->assertTrue($definition->isAbstract());
        }
    }

    #[RequiresMethod(ContainerBuilder::class, 'getAutoconfiguredAttributes')]
    public function testMinimalConfigPreSymf8(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['url' => 'sqlite3:///:memory:'],
                ],
            ],
        );

        self::assertInstanceOf(Connection::class, $container->get('event_sourcing.dbal_connection'));
        self::assertInstanceOf(DoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(SystemClock::class, $container->get('event_sourcing.clock'));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));

        self::assertFalse($container->has(EventBus::class));

        $attributes = $container->getAutoconfiguredAttributes();
        foreach ([Aggregate::class, Event::class] as $class) {
            $definition = new ChildDefinition('');
            $attributes[$class]($definition);

            $this->assertSame(
                [['source' => sprintf('with #[%s] attribute', $class)]],
                $definition->getTag('container.excluded'),
            );
            $this->assertTrue($definition->isAbstract());
        }
    }

    public function testConnectionService(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
        );

        self::assertInstanceOf(Connection::class, $container->get('event_sourcing.dbal_connection'));
        self::assertInstanceOf(DoctrineDbalStore::class, $container->get(Store::class));
    }

    public function testProjectionConnection(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'url' => 'sqlite3:///:memory:',
                        'provide_dedicated_connection' => true,
                    ],
                ],
            ],
        );

        $eventSourcingConnection = $container->get('event_sourcing.dbal_connection');
        $projectionConnection = $container->get('event_sourcing.dbal_public_connection');

        self::assertInstanceOf(Connection::class, $eventSourcingConnection);
        self::assertInstanceOf(Connection::class, $projectionConnection);

        self::assertNotSame($eventSourcingConnection, $projectionConnection);
    }

    public function testCustomStore(): void
    {
        $store = $this->createMock(Store::class);

        $container = new ContainerBuilder();

        $container->set('my_store', $store);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => [
                        'type' => 'custom',
                        'service' => 'my_store',
                    ],
                ],
            ],
        );

        self::assertSame($store, $container->get(Store::class));
    }

    public function testCustomStoreMissingService(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $store = $this->createMock(Store::class);

        $container = new ContainerBuilder();

        $container->set('my_store', $store);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => ['type' => 'custom'],
                ],
            ],
        );
    }

    public function testStreamStore(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => ['type' => 'dbal_stream'],
                ],
            ],
        );

        self::assertInstanceOf(StreamStore::class, $container->get(Store::class));
    }

    public function testInMemoryStore(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => ['type' => 'in_memory'],
                ],
            ],
        );

        self::assertInstanceOf(InMemoryStore::class, $container->get(Store::class));
    }

    public function testReadOnlyStore(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => ['read_only' => true],
                ],
            ],
        );

        self::assertInstanceOf(ReadOnlyStore::class, $container->get(Store::class));
    }

    public function testMigrateStore(): void
    {
        $container = new ContainerBuilder();

        $container->register('my_translator', ExcludeEventWithHeaderTranslator::class)
            ->setArguments([ArchivedHeader::class]);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => [
                        'migrate_to_new_store' => [
                            'type' => 'dbal_stream',
                            'translators' => [
                                'my_translator',
                                RecalculatePlayheadTranslator::class,
                                AggregateToStreamHeaderTranslator::class,
                            ],
                        ],
                    ],
                ],
            ],
        );

        self::assertInstanceOf(DoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get('event_sourcing.store.new_store'));
        self::assertInstanceOf(StoreMigrateCommand::class, $container->get(StoreMigrateCommand::class));

        self::assertEquals(
            [
                'my_translator' => [
                    ['priority' => 0],
                ],
                RecalculatePlayheadTranslator::class => [
                    ['priority' => -1],
                ],
                AggregateToStreamHeaderTranslator::class => [
                    ['priority' => -2],
                ],
            ],
            $container->findTaggedServiceIds('event_sourcing.translator'),
        );
    }

    public function testStreamReadOnlyStore(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => [
                        'type' => 'dbal_stream',
                        'read_only' => true,
                    ],
                ],
            ],
        );

        self::assertInstanceOf(StreamReadOnlyStore::class, $container->get(Store::class));
    }

    public function testSymfonyEventBus(): void
    {
        $eventBus = $this->createMock(MessageBusInterface::class);

        $container = new ContainerBuilder();
        $container->set('my_event_bus', $eventBus);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'event_bus' => [
                        'type' => 'symfony',
                        'service' => 'my_event_bus',
                    ],
                ],
            ],
        );

        self::assertEquals(new SymfonyEventBus($eventBus), $container->get(EventBus::class));
    }

    public function testPsr14EventBus(): void
    {
        $eventBus = $this->createMock(EventDispatcherInterface::class);

        $container = new ContainerBuilder();
        $container->set('my_event_bus', $eventBus);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'event_bus' => [
                        'type' => 'psr14',
                        'service' => 'my_event_bus',
                    ],
                ],
            ],
        );

        self::assertEquals(new Psr14EventBus($eventBus), $container->get(EventBus::class));
    }

    public function testCustomEventBus(): void
    {
        $eventBus = $this->createMock(EventBus::class);

        $container = new ContainerBuilder();
        $container->set('my_event_bus', $eventBus);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'event_bus' => [
                        'type' => 'custom',
                        'service' => 'my_event_bus',
                    ],
                ],
            ],
        );

        self::assertEquals($eventBus, $container->get(EventBus::class));
    }

    public function testListener(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(Listener1::class, new Definition(Listener1::class))
            ->addTag('event_sourcing.listener', ['priority' => -64]);
        $container->setDefinition(Listener2::class, new Definition(Listener2::class))
            ->addTag('event_sourcing.listener');

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'event_bus' => true,
                ],
            ],
        );

        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertEquals(
            [
                'Patchlevel\EventSourcingBundle\Tests\Fixtures\Listener1' => [
                    ['priority' => -64],
                ],
                'Patchlevel\EventSourcingBundle\Tests\Fixtures\Listener2' => [
                    [],
                ],
            ],
            $container->findTaggedServiceIds('event_sourcing.listener'),
        );
    }

    public function testAutoconfigureListener(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(Listener1::class, new Definition(Listener1::class))
            ->setAutoconfigured(true);
        $container->setDefinition(Listener2::class, new Definition(Listener1::class))
            ->setAutoconfigured(false);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'event_bus' => true,
                ],
            ],
        );

        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertEquals(
            [
                'Patchlevel\EventSourcingBundle\Tests\Fixtures\Listener1' => [
                    ['priority' => 0],
                ],
            ],
            $container->findTaggedServiceIds('event_sourcing.listener'),
        );
    }

    public function testCommandHandler(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                    'aggregate_handlers' => ['bus' => 'command.bus'],
                ],
            ],
        );

        $handler = $container->get('event_sourcing.handler.profile.create');

        self::assertInstanceOf(CreateAggregateHandler::class, $handler);

        $definition = $container->getDefinition('event_sourcing.handler.profile.create');
        $tags = $definition->getTag('messenger.message_handler');

        self::assertCount(1, $tags);

        $tag = $tags[0];

        self::assertEquals(CreateProfile::class, $tag['handles']);
        self::assertEquals('command.bus', $tag['bus']);
    }

    public function testCommandBusAndLegacyConfigurationNotAllowed(): void
    {
        $container = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Remove legacy aggregate_handlers configuration when using command_bus');

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                    'aggregate_handlers' => ['bus' => 'command.bus'],
                    'command_bus' => ['service' => 'command.bus'],
                ],
            ],
        );
    }

    public function testCommandBus(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                    'command_bus' => ['service' => 'command.bus'],
                ],
            ],
        );

        $handler = $container->get('event_sourcing.handler.profile.create');

        self::assertInstanceOf(CreateAggregateHandler::class, $handler);

        $definition = $container->getDefinition('event_sourcing.handler.profile.create');
        $tags = $definition->getTag('messenger.message_handler');

        self::assertCount(1, $tags);

        $tag = $tags[0];

        self::assertEquals(CreateProfile::class, $tag['handles']);
        self::assertEquals('command.bus', $tag['bus']);
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
    }

    public function testQueryBus(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition(ProfileProjector::class, new Definition(ProfileProjector::class))
            ->setAutoconfigured(true);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                    'query_bus' => ['service' => 'query.bus'],
                ],
            ],
        );

        $definition = $container->getDefinition(ProfileProjector::class);
        $tags = $definition->getTag('messenger.message_handler');

        self::assertCount(1, $tags);

        $tag = $tags[0];

        self::assertEquals('query', $tag['method']);
        self::assertEquals(QueryFoo::class, $tag['handles']);
        self::assertEquals('query.bus', $tag['bus']);
        self::assertInstanceOf(SymfonyQueryBus::class, $container->get(QueryBus::class));

        $handler = $container->get(ProfileProjector::class);

        self::assertEquals('foo', $handler->{$tag['method']}(new QueryFoo('foo')));
    }

    public function testMessageLoader(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
        );

        $messageLoader = $container->get(MessageLoader::class);

        self::assertInstanceOf(StoreMessageLoader::class, $messageLoader);
    }

    public function testGapDetection(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'gap_detection' => ['enabled' => true],
                    ],
                ],
            ],
        );

        $messageLoader = $container->get(MessageLoader::class);

        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $messageLoader);
    }

    public function testGapDetectionWithExplicitValues(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'gap_detection' => [
                            'enabled' => true,
                            'retries_in_ms' => [0, 1, 2],
                            'detection_window' => 'PT3M',
                        ],
                    ],
                ],
            ],
        );

        $messageLoader = $container->get(MessageLoader::class);

        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $messageLoader);
    }

    public function testSubscriptionCleanupWithDoctrine(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);

        $container = new ContainerBuilder();
        $container->set('doctrine', $registry);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => ['merge_orm_schema' => true],
                ],
            ],
        );

        $definition = $container->getDefinition(DbalCleanupTaskHandler::class);
        $tags = $definition->getTag('event_sourcing.cleanup_task_handler');

        self::assertCount(1, $tags);

        $cleaner = $container->get(Cleaner::class);
        self::assertInstanceOf(DefaultCleaner::class, $cleaner);

        $cleanupTaskHandler = $container->get(DbalCleanupTaskHandler::class);
        self::assertInstanceOf(DbalCleanupTaskHandler::class, $cleanupTaskHandler);
    }

    public function testSubscriptionCleanupWithProjectionConnection(): void
    {
        $container = new ContainerBuilder();
        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => [
                        'url' => 'sqlite3:///:memory:',
                        'provide_dedicated_connection' => true,
                    ],
                ],
            ],
        );

        $definition = $container->getDefinition(DbalCleanupTaskHandler::class);
        $tags = $definition->getTag('event_sourcing.cleanup_task_handler');

        self::assertCount(1, $tags);

        $cleaner = $container->get(Cleaner::class);
        self::assertInstanceOf(DefaultCleaner::class, $cleaner);

        $cleanupTaskHandler = $container->get(DbalCleanupTaskHandler::class);
        self::assertInstanceOf(DbalCleanupTaskHandler::class, $cleanupTaskHandler);
    }

    public function testSnapshotStore(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'snapshot_stores' => [
                        'default' => ['service' => 'cache.default'],
                    ],
                ],
            ],
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
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'snapshot_stores' => [
                        'default' => ['service' => 'cache.default'],
                    ],
                ],
            ],
        );

        self::assertInstanceOf(
            Psr6SnapshotAdapter::class,
            $container->get('event_sourcing.snapshot_store.adapter.default'),
        );
    }

    public function testPsr16SnapshotAdapter(): void
    {
        $simpleCache = $this->createMock(CacheInterface::class);

        $container = new ContainerBuilder();
        $container->set('simple_cache', $simpleCache);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'snapshot_stores' => [
                        'default' => [
                            'type' => 'psr16',
                            'service' => 'simple_cache',
                        ],
                    ],
                ],
            ],
        );

        self::assertInstanceOf(
            Psr16SnapshotAdapter::class,
            $container->get('event_sourcing.snapshot_store.adapter.default'),
        );
    }

    public function testCustomSnapshotAdapter(): void
    {
        $customSnapshotStore = $this->createMock(SnapshotStore::class);

        $container = new ContainerBuilder();
        $container->set('my_snapshot_store', $customSnapshotStore);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'snapshot_stores' => [
                        'default' => [
                            'type' => 'custom',
                            'service' => 'my_snapshot_store',
                        ],
                    ],
                ],
            ],
        );

        self::assertEquals($customSnapshotStore, $container->get('event_sourcing.snapshot_store.adapter.default'));
    }

    public function testEventRegistry(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'events' => [__DIR__ . '/../Fixtures'],
                ],
            ],
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
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                ],
            ],
        );

        $aggregateRegistry = $container->get(AggregateRootRegistry::class);

        self::assertInstanceOf(AggregateRootRegistry::class, $aggregateRegistry);
        self::assertTrue($aggregateRegistry->hasAggregateClass(Profile::class));
    }

    public function testMessageHeaderRegistry(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'headers' => [__DIR__ . '/../Fixtures'],
                ],
            ],
        );

        /** @var MessageHeaderRegistry $messageHeaderRegistry */
        $messageHeaderRegistry = $container->get(MessageHeaderRegistry::class);

        self::assertInstanceOf(MessageHeaderRegistry::class, $messageHeaderRegistry);
        self::assertTrue($messageHeaderRegistry->hasHeaderClass(CustomHeader::class));
    }

    public function testRepositoryManager(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                ],
            ],
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
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
        );

        self::assertInstanceOf(DatabaseCreateCommand::class, $container->get(DatabaseCreateCommand::class));
        self::assertInstanceOf(DatabaseDropCommand::class, $container->get(DatabaseDropCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(
            SubscriptionReactivateCommand::class,
            $container->get(SubscriptionReactivateCommand::class),
        );
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRefreshCommand::class, $container->get(SubscriptionRefreshCommand::class));
        self::assertInstanceOf(SchemaCreateCommand::class, $container->get(SchemaCreateCommand::class));
        self::assertInstanceOf(SchemaUpdateCommand::class, $container->get(SchemaUpdateCommand::class));
        self::assertInstanceOf(SchemaDropCommand::class, $container->get(SchemaDropCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
    }

    public function testMigrations(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
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
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
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
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'clock' => ['freeze' => '2020-01-01 22:00:00'],
                ],
            ],
        );

        $clock = $container->get('event_sourcing.clock');

        self::assertInstanceOf(FrozenClock::class, $clock);
        self::assertSame('2020-01-01 22:00:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testPsrClock(): void
    {
        $psrClock = $this->createMock(ClockInterface::class);

        $container = new ContainerBuilder();
        $container->set('clock', $psrClock);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'clock' => ['service' => 'clock'],
                ],
            ],
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
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
        );

        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
    }

    public function testRunSubscriptionEngineRepositoryManager(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'run_after_aggregate_save' => [
                            'ids' => ['a'],
                            'groups' => ['b'],
                            'limit' => 10,
                        ],
                    ],
                ],
            ],
        );

        self::assertInstanceOf(
            RunSubscriptionEngineRepositoryManager::class,
            $container->get(RepositoryManager::class),
        );
    }

    public function testSubscriptionEngineInMemoryStore(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'store' => ['type' => 'in_memory'],
                    ],
                ],
            ],
        );

        self::assertInstanceOf(InMemorySubscriptionStore::class, $container->get(SubscriptionStore::class));
    }

    public function testSubscriptionEngineStaticInMemoryStore(): void
    {
        $containerA = new ContainerBuilder();
        $containerB = new ContainerBuilder();

        $this->compileContainer(
            $containerA,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'store' => ['type' => 'static_in_memory'],
                    ],
                ],
            ],
        );

        $this->compileContainer(
            $containerB,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'store' => ['type' => 'static_in_memory'],
                    ],
                ],
            ],
        );

        self::assertSame(
            $containerA->get(SubscriptionStore::class),
            $containerB->get(SubscriptionStore::class),
        );
    }

    public function testCatchUpSubscriptionEngine(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'catch_up' => ['limit' => 10],
                    ],
                ],
            ],
        );

        self::assertInstanceOf(
            CatchUpSubscriptionEngine::class,
            $container->get(SubscriptionEngine::class),
        );
    }

    public function testSubscriberSameConnectionError(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition(ProfileProjector::class, new Definition(
            ProfileProjector::class,
            [new Reference('doctrine.dbal.eventstore_connection')],
        ))
            ->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Using the same database connection for the eventstore and projections is not allowed. This configuration may result in transaction conflicts due to DDL operations, leading to system instability. Please use separate connections for the eventstore and projections to ensure safe operation. Argument 1 on class Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileProjector.');

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
        );
    }

    public function testAutoconfigureSubscriber(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition(ProfileSubscriber::class, new Definition(ProfileSubscriber::class))
            ->setAutoconfigured(true);

        $container->setDefinition(ProfileProcessor::class, new Definition(ProfileProcessor::class))
            ->setAutoconfigured(true);

        $container->setDefinition(ProfileProjector::class, new Definition(ProfileProjector::class))
            ->setAutoconfigured(true);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
        );

        self::assertTrue($container->getDefinition(ProfileSubscriber::class)->hasTag('event_sourcing.subscriber'));
        self::assertTrue($container->getDefinition(ProfileProcessor::class)->hasTag('event_sourcing.subscriber'));
        self::assertTrue($container->getDefinition(ProfileProjector::class)->hasTag('event_sourcing.subscriber'));
    }

    public function testAutoconfigureArgumentResolver(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition(DummyArgumentResolver::class, new Definition(DummyArgumentResolver::class))
            ->setAutoconfigured(true);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
        );

        self::assertTrue($container->getDefinition(DummyArgumentResolver::class)->hasTag('event_sourcing.argument_resolver'));
        self::assertInstanceOf(
            TaggedIteratorArgument::class,
            $container->getDefinition(MetadataSubscriberAccessorRepository::class)->getArgument(2),
        );
        self::assertEquals(
            'event_sourcing.argument_resolver',
            $container->getDefinition(MetadataSubscriberAccessorRepository::class)->getArgument(2)->getTag(),
        );
    }

    public function testLegacyRetryStrategy(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'retry_strategy' => [
                            'base_delay' => 10,
                            'delay_factor' => 11,
                            'max_attempts' => 12,
                        ],
                    ],
                ],
            ],
        );

        $repository = $container->get(RetryStrategyRepository::class);

        self::assertInstanceOf(RetryStrategyRepository::class, $repository);
        self::assertInstanceOf(ClockBasedRetryStrategy::class, $repository->getDefaultRetryStrategy());
        self::assertInstanceOf(ClockBasedRetryStrategy::class, $repository->get('default'));
        self::assertInstanceOf(NoRetryStrategy::class, $repository->get('no_retry'));
    }

    public function testRetryStrategy(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'retry_strategies' => [
                            'default' => [
                                'type' => 'clock_based',
                                'options' => [
                                    'base_delay' => 10,
                                    'delay_factor' => 11,
                                    'max_attempts' => 12,
                                ],
                            ],
                            'no_retry' => ['type' => 'no_retry'],
                        ],
                    ],
                ],
            ],
        );

        $repository = $container->get(RetryStrategyRepository::class);

        self::assertInstanceOf(RetryStrategyRepository::class, $repository);
        self::assertInstanceOf(ClockBasedRetryStrategy::class, $repository->getDefaultRetryStrategy());
        self::assertInstanceOf(ClockBasedRetryStrategy::class, $repository->get('default'));
        self::assertInstanceOf(NoRetryStrategy::class, $repository->get('no_retry'));
    }

    public function testRetryStrategyCustom(): void
    {
        $retryStrategy = $this->createMock(RetryStrategy::class);

        $container = new ContainerBuilder();
        $container->set('my_retry_strategy', $retryStrategy);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'subscription' => [
                        'retry_strategies' => [
                            'default' => [
                                'type' => 'custom',
                                'service' => 'my_retry_strategy',
                            ],
                        ],
                    ],
                ],
            ],
        );

        $repository = $container->get(RetryStrategyRepository::class);

        self::assertInstanceOf(RetryStrategyRepository::class, $repository);
        self::assertEquals($retryStrategy, $repository->getDefaultRetryStrategy());
    }

    public function testSchemaMerge(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => ['merge_orm_schema' => true],
                ],
            ],
        );

        self::assertInstanceOf(DoctrineSchemaListener::class, $container->get(DoctrineSchemaListener::class));
        self::assertFalse($container->has(SchemaDirector::class));
        self::assertFalse($container->has(DoctrineSchemaProvider::class));
        self::assertFalse($container->has(DatabaseCreateCommand::class));
        self::assertFalse($container->has('event_sourcing.command.migration_diff'));
    }

    public function testLegacyHydrator(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition(DummyGuesser::class, new Definition(DummyGuesser::class))
            ->setAutoconfigured(true);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                ],
            ],
        );

        self::assertInstanceOf(MetadataHydrator::class, $container->get(Hydrator::class));

        self::assertEquals(
            [
                BuiltInGuesser::class => [
                    ['priority' => -64],
                ],
                SymfonyGuesser::class => [
                    ['priority' => -32],
                ],
                DummyGuesser::class => [
                    [],
                ],
            ],
            $container->findTaggedServiceIds('event_sourcing.hydrator.guesser'),
        );
    }

    public function testHydrator(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition(DummyExtension::class, new Definition(DummyExtension::class))
            ->setAutoconfigured(true);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'hydrator' => [
                        'enabled' => true,
                        'lifecycle' => ['enabled' => true],
                        'cryptography' => ['enabled' => true],
                    ],
                ],
            ],
        );

        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));

        self::assertEquals(
            [
                CoreExtension::class => [
                    [],
                ],
                SymfonyExtension::class => [
                    [],
                ],
                DummyExtension::class => [
                    [],
                ],
                LifecycleExtension::class => [
                    [],
                ],
                CryptographyExtension::class => [
                    [],
                ],
            ],
            $container->findTaggedServiceIds('event_sourcing.hydrator.extension'),
        );
    }

    public function testCryptography(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'cryptography' => ['algorithm' => 'aes256'],
                ],
            ],
        );

        self::assertInstanceOf(PersonalDataPayloadCryptographer::class, $container->get(PayloadCryptographer::class));
    }

    public function testFullBuild(): void
    {
        $psrClock = $this->createMock(ClockInterface::class);

        $container = new ContainerBuilder();
        $container->set('clock', $psrClock);

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'store' => [
                        'options' => ['table_name' => 'event_store'],
                    ],
                    'clock' => ['service' => 'clock'],
                    'event_bus' => ['type' => 'default'],
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
                    'cryptography' => ['algorithm' => 'aes256'],
                    'subscription' => [
                        'catch_up' => ['limit' => 10],
                        'throw_on_error' => true,
                    ],
                ],
            ],
        );

        self::assertInstanceOf(Connection::class, $container->get('event_sourcing.dbal_connection'));
        self::assertInstanceOf(DoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(RepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(ResetServicesListener::class, $container->get(ResetServicesListener::class));
    }

    public function testNamedRepository(): void
    {
        $container = new ContainerBuilder();

        $this->compileContainer(
            $container,
            [
                'patchlevel_event_sourcing' => [
                    'connection' => ['service' => 'doctrine.dbal.eventstore_connection'],
                    'aggregates' => [__DIR__ . '/../Fixtures'],
                ],
            ],
        );

        $profileRepository = $container->get('event_sourcing.profile.repository');
        self::assertInstanceOf(Repository::class, $profileRepository);

        $namedArgumentProfileRepository = $container->get(Repository::class . ' $profileRepository');
        self::assertInstanceOf(Repository::class, $namedArgumentProfileRepository);

        self::assertSame($profileRepository, $namedArgumentProfileRepository);
    }

    /** @param array{patchlevel_event_sourcing: array<string, mixed>} $config */
    private function compileContainer(ContainerBuilder $container, array $config): void
    {
        $bundle = new PatchlevelEventSourcingBundle();
        $bundle->build($container);

        $container->setParameter('kernel.project_dir', __DIR__);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $container->set('doctrine.dbal.eventstore_connection', $connection);
        $container->set('event.bus', $this->createMock(MessageBusInterface::class));
        $container->set('command.bus', $this->createMock(MessageBusInterface::class));
        $container->set('query.bus', $this->createMock(MessageBusInterface::class));
        $container->set('cache.default', $this->createMock(CacheItemPoolInterface::class));
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->set('services_resetter', new ServicesResetter(new ArrayObject(), []));
        $container->set(LoggerInterface::class, $this->createMock(LoggerInterface::class));

        $extension = new PatchlevelEventSourcingExtension();
        $extension->load($config, $container);

        $compilerPassConfig = $container->getCompilerPassConfig();
        $compilerPassConfig->setRemovingPasses([]);
        $compilerPassConfig->addPass(new TestCaseAllPublicCompilerPass());

        $container->compile();

        (new XmlDumper($container))->dump();
    }
}
