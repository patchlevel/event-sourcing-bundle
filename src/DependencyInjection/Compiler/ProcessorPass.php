<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection\Compiler;

use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ProcessorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(DefaultEventBus::class)) {
            $this->processDefaultEventBus($container);
        }

        if ($container->hasDefinition(SymfonyEventBus::class)) {
            $this->processSymfonyEventBus($container);
        }
    }

    private function processDefaultEventBus(ContainerBuilder $container): void
    {
        /** @var array<int, array<string>> $processors */
        $processors = [];

        foreach ($container->findTaggedServiceIds('event_sourcing.processor') as $id => $attributes) {
            $priority = $attributes['priority'] ?? 0;
            $processors[$priority][] = $id;
        }

        ksort($processors);

        $eventBus = $container->getDefinition(DefaultEventBus::class);

        foreach ($processors as $id) {
            $eventBus->addMethodCall('addListener', [new Reference($id)]);
        }
    }

    private function processSymfonyEventBus(ContainerBuilder $container): void
    {
        $eventBusService = $container->getParameter('event_sourcing.event_bus_service');

        foreach ($container->findTaggedServiceIds('event_sourcing.processor') as $id => $attributes) {
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
