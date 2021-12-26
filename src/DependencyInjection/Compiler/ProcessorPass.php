<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection\Compiler;

use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function ksort;

class ProcessorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(DefaultEventBus::class)) {
            $this->processDefaultEventBus($container);
        }

        if (!$container->hasDefinition(SymfonyEventBus::class)) {
            return;
        }

        $this->processSymfonyEventBus($container);
    }

    private function processDefaultEventBus(ContainerBuilder $container): void
    {
        /** @var array<int, list<string>> $groupedProcessors */
        $groupedProcessors = [];

        /**
         * @var list<array{priority: ?int}> $tags
         */
        foreach ($container->findTaggedServiceIds('event_sourcing.processor') as $id => $tags) {
            foreach ($tags as $attributes) {
                $priority = $attributes['priority'] ?? 0;
                $groupedProcessors[$priority][] = $id;
            }
        }

        krsort($groupedProcessors);
        $eventBus = $container->getDefinition(DefaultEventBus::class);

        foreach ($groupedProcessors as $processors) {
            foreach ($processors as $id) {
                $eventBus->addMethodCall('addListener', [new Reference($id)]);
            }
        }
    }

    private function processSymfonyEventBus(ContainerBuilder $container): void
    {
        $eventBusService = $container->getParameter('event_sourcing.event_bus_service');

        /**
         * @var list<array{priority: ?int}> $tags
         */
        foreach ($container->findTaggedServiceIds('event_sourcing.processor') as $id => $tags) {
            foreach ($tags as $attributes) {
                $processor = $container->getDefinition($id);
                $processor->addTag(
                    'messenger.message_handler',
                    [
                        'bus' => $eventBusService,
                        'priority' => $attributes['priority'] ?? 0,
                    ]
                );
            }
        }
    }
}
