<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowAggregateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionBootCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionReactivateCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRemoveCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRunCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionSetupCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionTeardownCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Cryptography\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\Debug\Trace\TraceableSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Debug\Trace\TraceDecorator;
use Patchlevel\EventSourcing\Debug\Trace\TraceStack;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\EventBus\Psr14EventBus;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaSubscriber;
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
use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberHelper;
use Patchlevel\EventSourcingBundle\Attribute\AsListener;
use Patchlevel\EventSourcingBundle\DataCollector\EventSourcingCollector;
use Patchlevel\EventSourcingBundle\DataCollector\MessageListener;
use Patchlevel\EventSourcingBundle\Doctrine\DbalConnectionFactory;
use Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcingBundle\RequestListener\SubscriptionRebuildAfterFileChangeListener;
use Patchlevel\EventSourcingBundle\RequestListener\TraceListener;
use Patchlevel\EventSourcingBundle\ValueResolver\AggregateRootIdValueResolver;
use Patchlevel\Hydrator\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipher;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\MetadataHydrator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

use function class_exists;
use function sprintf;

/** @psalm-import-type Config from Configuration */
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
        $this->configureSubscription($config, $container);
        $this->configureCryptography($config, $container);
        $this->configureDebugging($config, $container);
        $this->configureMigration($config, $container);
        $this->configureValueResolver($container);
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

        $container->register(AttributeMessageHeaderRegistryFactory::class);
        $container->setAlias(MessageHeaderRegistryFactory::class, AttributeMessageHeaderRegistryFactory::class);

        $container->register(MessageHeaderRegistry::class)
            ->setFactory([new Reference(MessageHeaderRegistryFactory::class), 'create'])
            ->setArguments([[]]);

        $container->register(DefaultHeadersSerializer::class)
            ->setArguments([
                new Reference(MessageHeaderRegistry::class),
                new Reference(Hydrator::class),
                new Reference(Encoder::class),
            ]);

        $container->setAlias(HeadersSerializer::class, DefaultHeadersSerializer::class);
    }

    /** @param Config $config */
    private function configureEventBus(array $config, ContainerBuilder $container): void
    {
        if (!$config['event_bus']['enabled']) {
            return;
        }

        $container->registerAttributeForAutoconfiguration(
            AsListener::class,
            static function (ChildDefinition $definition, AsListener $attribute): void {
                $definition->addTag('event_sourcing.listener', [
                    'priority' => $attribute->priority,
                ]);
            },
        );

        if ($config['event_bus']['type'] === 'default') {
            $container->register(AttributeListenerProvider::class)
                ->setArguments([new TaggedIteratorArgument('event_sourcing.listener')]);

            $container->setAlias(ListenerProvider::class, AttributeListenerProvider::class);

            $container->register(DefaultConsumer::class)
                ->setArguments([
                    new Reference(ListenerProvider::class),
                    new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('monolog.logger', ['channel' => 'event_sourcing']);

            $container->setAlias(Consumer::class, DefaultConsumer::class);

            $container
                ->register(DefaultEventBus::class)
                ->setArguments([
                    new Reference(Consumer::class),
                    new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('monolog.logger', ['channel' => 'event_sourcing']);

            $container->setAlias(EventBus::class, DefaultEventBus::class);

            return;
        }

        if ($config['event_bus']['type'] === 'symfony') {
            $container->register(SymfonyEventBus::class)
                ->setArguments([new Reference($config['event_bus']['service'])]);

            $container->setAlias(EventBus::class, SymfonyEventBus::class);

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
    private function configureSubscription(array $config, ContainerBuilder $container): void
    {
        $attributes = [Subscriber::class, Processor::class, Projector::class];

        foreach ($attributes as $attribute) {
            $container->registerAttributeForAutoconfiguration(
                $attribute,
                static function (ChildDefinition $definition): void {
                    $definition->addTag('event_sourcing.subscriber');
                },
            );
        }

        $container->register(AttributeSubscriberMetadataFactory::class);
        $container->setAlias(SubscriberMetadataFactory::class, AttributeSubscriberMetadataFactory::class);

        $container->register(ClockBasedRetryStrategy::class)
            ->setArguments([
                new Reference('event_sourcing.clock'),
                $config['subscription']['retry_strategy']['base_delay'],
                $config['subscription']['retry_strategy']['delay_factor'],
                $config['subscription']['retry_strategy']['max_attempts'],
            ]);

        $container->setAlias(RetryStrategy::class, ClockBasedRetryStrategy::class);

        $container->register(SubscriberHelper::class)
            ->setArguments([new Reference(SubscriberMetadataFactory::class)]);

        $container->register(DoctrineSubscriptionStore::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
            ])
            ->addTag('event_sourcing.doctrine_schema_configurator');

        $container->setAlias(SubscriptionStore::class, DoctrineSubscriptionStore::class);

        $container->register(MetadataSubscriberAccessorRepository::class)
            ->setArguments([
                new TaggedIteratorArgument('event_sourcing.subscriber'),
                new Reference(SubscriberMetadataFactory::class),
            ]);

        $container->setAlias(SubscriberAccessorRepository::class, MetadataSubscriberAccessorRepository::class);

        $container->register(DefaultSubscriptionEngine::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(SubscriptionStore::class),
                new Reference(SubscriberAccessorRepository::class),
                new Reference(RetryStrategy::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('monolog.logger', ['channel' => 'event_sourcing']);

        $container->setAlias(SubscriptionEngine::class, DefaultSubscriptionEngine::class);

        if ($config['subscription']['throw_on_error']['enabled']) {
            $container->register(ThrowOnErrorSubscriptionEngine::class)
                ->setDecoratedService(SubscriptionEngine::class)
                ->setArguments([
                    new Reference('.inner'),
                ]);
        }

        if ($config['subscription']['catch_up']['enabled']) {
            $container->register(CatchUpSubscriptionEngine::class)
                ->setDecoratedService(SubscriptionEngine::class)
                ->setArguments([
                    new Reference('.inner'),
                    $config['subscription']['catch_up']['limit'],
                ]);
        }

        if ($config['subscription']['run_after_aggregate_save']['enabled']) {
            $container->register(RunSubscriptionEngineRepositoryManager::class)
                ->setDecoratedService(RepositoryManager::class)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(SubscriptionEngine::class),
                    $config['subscription']['run_after_aggregate_save']['ids'] ?: null,
                    $config['subscription']['run_after_aggregate_save']['groups'] ?: null,
                    $config['subscription']['run_after_aggregate_save']['limit'],
                ]);
        }

        if (!$config['subscription']['rebuild_after_file_change']) {
            return;
        }

        $container->register(SubscriptionRebuildAfterFileChangeListener::class)
            ->setArguments([
                new Reference(SubscriptionEngine::class),
                new TaggedIteratorArgument('event_sourcing.subscriber'),
                new Reference('cache.app'),
                new Reference(SubscriberMetadataFactory::class),
            ])
            ->addTag('kernel.event_listener', [
                'event' => 'kernel.request',
                'priority' => 200,
                'method' => 'onKernelRequest',
            ])
            ->addTag('kernel.event_listener', [
                'priority' => 200,
                'event' => 'console.command',
                'method' => 'onConsoleCommand',
            ]);
    }

    private function configureHydrator(ContainerBuilder $container): void
    {
        $container->register(AttributeMetadataFactory::class);
        $container->setAlias(MetadataFactory::class, AttributeMetadataFactory::class);

        $container->register(MetadataHydrator::class)
            ->setArguments([
                new Reference(MetadataFactory::class),
                new Reference(
                    PayloadCryptographer::class,
                    ContainerInterface::IGNORE_ON_INVALID_REFERENCE,
                ),
            ]);

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

        if ($config['connection']['url'] !== null) {
            $container->register('event_sourcing.dbal_connection', Connection::class)
                ->setFactory([DbalConnectionFactory::class, 'createConnection'])
                ->setArguments([
                    $config['connection']['url'],
                ]);

            return;
        }

        if ($config['connection']['service'] === null) {
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
                new Reference(HeadersSerializer::class),
                $config['store']['options'],
            ])
            ->addTag('event_sourcing.doctrine_schema_configurator');

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
            ->setArguments([
                $adapters,
                new Reference(Hydrator::class),
                new Reference(AggregateRootMetadataFactory::class),
            ]);

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
                new Reference(EventBus::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
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
                new Reference(HeadersSerializer::class),
            ])
            ->addTag('console.command');

        $container->register(ShowAggregateCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(EventSerializer::class),
                new Reference(HeadersSerializer::class),
                new Reference(AggregateRootRegistry::class),
            ])
            ->addTag('console.command');

        $container->register(WatchCommand::class)
            ->setArguments([
                new Reference(Store::class),
                new Reference(EventSerializer::class),
                new Reference(HeadersSerializer::class),
            ])
            ->addTag('console.command');

        $container->register(DebugCommand::class)
            ->setArguments([
                new Reference(AggregateRootRegistry::class),
                new Reference(EventRegistry::class),
            ])
            ->addTag('console.command');

        $container->register(SubscriptionSetupCommand::class)
            ->setArguments([
                new Reference(SubscriptionEngine::class),
            ])
            ->addTag('console.command');

        $container->register(SubscriptionBootCommand::class)
            ->setArguments([
                new Reference(SubscriptionEngine::class),
                new Reference(Store::class),
            ])
            ->addTag('console.command');

        $container->register(SubscriptionRunCommand::class)
            ->setArguments([
                new Reference(SubscriptionEngine::class),
                new Reference(Store::class),
            ])
            ->addTag('console.command');

        $container->register(SubscriptionTeardownCommand::class)
            ->setArguments([
                new Reference(SubscriptionEngine::class),
            ])
            ->addTag('console.command');

        $container->register(SubscriptionRemoveCommand::class)
            ->setArguments([
                new Reference(SubscriptionEngine::class),
            ])
            ->addTag('console.command');

        $container->register(SubscriptionStatusCommand::class)
            ->setArguments([
                new Reference(SubscriptionEngine::class),
            ])
            ->addTag('console.command');

        $container->register(SubscriptionReactivateCommand::class)
            ->setArguments([
                new Reference(SubscriptionEngine::class),
            ])
            ->addTag('console.command');
    }

    /** @param Config $config */
    private function configureMigration(array $config, ContainerBuilder $container): void
    {
        if (!class_exists(DependencyFactory::class) || $config['store']['merge_orm_schema'] !== false) {
            return;
        }

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
            ->addTag('event_sourcing.listener')
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

        if ($config['clock']['service'] !== null) {
            $container->setAlias('event_sourcing.clock', $config['clock']['service']);

            return;
        }

        $container->register(SystemClock::class);
        $container->setAlias('event_sourcing.clock', SystemClock::class);
    }

    /** @param Config $config */
    private function configureSchema(array $config, ContainerBuilder $container): void
    {
        $container->register(ChainDoctrineSchemaConfigurator::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.doctrine_schema_configurator')]);

        $container->setAlias(DoctrineSchemaConfigurator::class, ChainDoctrineSchemaConfigurator::class);

        if ($config['store']['merge_orm_schema']) {
            $container->register(DoctrineSchemaSubscriber::class)
                ->setArguments([new Reference(DoctrineSchemaConfigurator::class)])
                ->addTag('doctrine.event_subscriber');

            return;
        }

        $container->register(DoctrineSchemaDirector::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
                new Reference(DoctrineSchemaConfigurator::class),
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

    /** @param Config $config */
    private function configureCryptography(array $config, ContainerBuilder $container): void
    {
        if (!$config['cryptography']['enabled']) {
            return;
        }

        $container->register(OpensslCipherKeyFactory::class)
            ->setArguments([
                $config['cryptography']['algorithm'],
            ]);
        $container->setAlias(CipherKeyFactory::class, OpensslCipherKeyFactory::class);

        $container->register(DoctrineCipherKeyStore::class)
            ->setArguments([
                new Reference('event_sourcing.dbal_connection'),
            ])
            ->addTag('event_sourcing.doctrine_schema_configurator');
        $container->setAlias(CipherKeyStore::class, DoctrineCipherKeyStore::class);

        $container->register(OpensslCipher::class);
        $container->setAlias(Cipher::class, OpensslCipher::class);

        $container->register(PersonalDataPayloadCryptographer::class)
            ->setArguments([
                new Reference(CipherKeyStore::class),
                new Reference(CipherKeyFactory::class),
                new Reference(Cipher::class),
            ]);

        $container->setAlias(PayloadCryptographer::class, PersonalDataPayloadCryptographer::class);
    }

    /** @param Config $config */
    private function configureDebugging(array $config, ContainerBuilder $container): void
    {
        if (!$config['debug']['trace']) {
            return;
        }

        $container->register(TraceStack::class);

        $container->register(TraceDecorator::class)
            ->setArguments([
                new Reference(TraceStack::class),
            ])
            ->addTag('event_sourcing.message_decorator');

        $container->register(TraceableSubscriberAccessorRepository::class)
            ->setDecoratedService(SubscriberAccessorRepository::class)
            ->setArguments([
                new Reference('.inner'),
                new Reference(TraceStack::class),
            ]);

        $container->register(TraceListener::class)
            ->setArguments([
                new Reference(TraceStack::class),
            ])
            ->addTag('kernel.event_listener', [
                'priority' => 0,
                'event' => 'console.command',
                'method' => 'onConsoleCommand',
            ])
            ->addTag('kernel.event_listener', [
                'priority' => 0,
                'event' => 'console.terminate',
                'method' => 'onConsoleTerminate',
            ])
            ->addTag('kernel.event_listener', [
                'priority' => 0,
                'event' => 'kernel.request',
                'method' => 'onRequest',
            ])
            ->addTag('kernel.event_listener', [
                'priority' => 0,
                'event' => 'kernel.response',
                'method' => 'onResponse',
            ]);
    }

    private function configureValueResolver(ContainerBuilder $container): void
    {
        $container->register(AggregateRootIdValueResolver::class)
            ->addTag('controller.argument_value_resolver', ['priority' => 200]);
    }
}
