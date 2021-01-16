<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
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
        $this->configureRepositories($config, $container);
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

        $container->register(ProjectionRepository::class)
            ->setArguments([new TaggedIteratorArgument('event_sourcing.projection')]);
    }

    private function configureStorage(array $config, ContainerBuilder $container): void
    {
        $dbalConnectionId = sprintf('doctrine.dbal.%s_connection', $config['dbal_connection']);

        if ($config['storage_type'] === 'single_table') {
            $container->register(SingleTableStore::class)
                ->setArguments([
                    new Reference($dbalConnectionId),
                    $config['aggregates'],
                ]);
            $container->setAlias(Store::class, SingleTableStore::class);

            return;
        }

        $container->register(MultiTableStore::class)
            ->setArguments([
                new Reference($dbalConnectionId),
                $config['aggregates'],
            ]);
        $container->setAlias(Store::class, MultiTableStore::class);
    }

    private function configureRepositories(array $config, ContainerBuilder $container): void
    {
        foreach ($config['aggregates'] as  $aggregateClass => $aggregateName) {
            $id = sprintf('event_sourcing.%s_repository', $aggregateName);

            $container->register($id, Repository::class)
                ->setArguments([
                    new Reference(Store::class),
                    new Reference(EventBus::class),
                    $aggregateClass,
                ]);
        }
    }
}
