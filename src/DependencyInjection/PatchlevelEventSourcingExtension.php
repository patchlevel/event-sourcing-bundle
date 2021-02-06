<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Console\Command\ProjectionCreateCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand;
use Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
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

use function sprintf;

class PatchlevelEventSourcingExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->configureEventBus($config, $container);
        $this->configureProjection($config, $container);
        $this->configureStorage($config, $container);
        $this->configureAggregates($config, $container);
        $this->configureCommands($config, $container);

        if ($config['watch_server']['enabled']) {
            $this->configureWatchServer($config, $container);
        }
    }

    private function configureEventBus(array $config, ContainerBuilder $container): void
    {
        $container->register(SymfonyEventBus::class)
            ->setArguments([new Reference($config['message_bus'])]);

        $container->setAlias(EventBus::class, SymfonyEventBus::class);

        $container->registerForAutoconfiguration(Listener::class)
            ->addTag('messenger.message_handler', ['bus' => $config['message_bus']]);
    }

    private function configureProjection(array $config, ContainerBuilder $container): void
    {
        $container->register(ProjectionListener::class)
            ->setArguments([new Reference(ProjectionRepository::class)])
            ->addTag('messenger.message_handler', ['bus' => $config['message_bus']]);

        $container->registerForAutoconfiguration(Projection::class)
            ->addTag('event_sourcing.projection');

        $container->register(DefaultProjectionRepository::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.projection')]);

        $container->setAlias(ProjectionRepository::class, DefaultProjectionRepository::class);
    }

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
                    $config['store']['options']['table_name'] ?? 'eventstore'
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

    private function configureCommands(array $config, ContainerBuilder $container): void
    {
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
                $config['aggregates']
            ])
            ->addTag('console.command');
    }

    private function configureWatchServer(array $config, ContainerBuilder $container): void
    {
        $container->register(WatchServerClient::class)
            ->setArguments([$config['watch_server']['host']]);

        $container->register(WatchListener::class)
            ->setArguments([new Reference(WatchServerClient::class)])
            ->addTag('messenger.message_handler', ['bus' => $config['message_bus']]);

        $container->register(WatchServer::class)
            ->setArguments([$config['watch_server']['host']]);

        $container->register(WatchCommand::class)
            ->setArguments([
                new Reference(WatchServer::class),
            ])
            ->addTag('console.command');
    }
}
