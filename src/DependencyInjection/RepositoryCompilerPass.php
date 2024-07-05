<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/** @interal */
final class RepositoryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $aggregateRootRegistry = $container->get(AggregateRootRegistry::class);

        if (!$aggregateRootRegistry instanceof AggregateRootRegistry) {
            return;
        }

        foreach ($aggregateRootRegistry->aggregateNames() as $aggregateName) {
            $aggregateRepositoryName = $aggregateName . 'Repository';
            $aggregateRepositoryId = 'event_sourcing.' . $aggregateName . '.repository';

            $definition = new Definition(Repository::class);
            $definition->setPublic(false);
            $definition->setFactory([new Reference(RepositoryManager::class), 'get']);
            $definition->setArgument(0, $aggregateRootRegistry->aggregateClass($aggregateName));

            $container->setDefinition($aggregateRepositoryId, $definition);
            $container->registerAliasForArgument($aggregateRepositoryId, Repository::class, $aggregateRepositoryName)->setPublic(false);
        }
    }
}
