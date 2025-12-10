<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/** @internal */
final class RepositoryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getParameter('event_sourcing.aggregates') as $aggregateName => $aggregateClass) {
            $aggregateRepositoryName = $aggregateName . 'Repository';
            $aggregateRepositoryId = 'event_sourcing.' . $aggregateName . '.repository';

            $definition = new Definition(Repository::class);
            $definition->setPublic(false);
            $definition->setFactory([new Reference(RepositoryManager::class), 'get']);
            $definition->setArgument(0, $aggregateClass);

            $container->setDefinition($aggregateRepositoryId, $definition);
            $container->registerAliasForArgument($aggregateRepositoryId, Repository::class, $aggregateRepositoryName)->setPublic(false);
        }
    }
}
