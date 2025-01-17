<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\Handler\UpdateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\HandlerFinder;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function sprintf;
use function strtolower;

/** @internal */
final class HandlerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('patchlevel_event_sourcing.aggregate_handlers.bus')) {
            return;
        }

        $bus = $container->getParameter('patchlevel_event_sourcing.aggregate_handlers.bus');

        /** @var AggregateRootRegistry $aggregateRootRegistry */
        $aggregateRootRegistry = $container->get(AggregateRootRegistry::class);

        foreach ($aggregateRootRegistry->aggregateClasses() as $aggregateName => $aggregateClass) {
            $parameterResolverId = sprintf('.event_sourcing.handler_parameter_resolver.%s', $aggregateName);

            foreach (HandlerFinder::findInClass($aggregateClass) as $aggregateHandler) {
                $handlerId = strtolower(sprintf('event_sourcing.handler.%s.%s', $aggregateName, $aggregateHandler->method));
                $handlerClass = $aggregateHandler->static ? CreateAggregateHandler::class : UpdateAggregateHandler::class;

                $container->register($handlerId, $handlerClass)
                    ->setArguments([
                        new Reference(RepositoryManager::class),
                        $aggregateClass,
                        $aggregateHandler->method,
                        new Reference($parameterResolverId),
                    ])
                    ->addTag('messenger.message_handler', [
                        'handles' => $aggregateHandler->commandClass,
                        'bus' => $bus,
                    ]);
            }
        }
    }
}
