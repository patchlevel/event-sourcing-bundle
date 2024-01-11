<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection\Compiler;

use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function krsort;

final class ProcessorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(ListenerProvider::class)) {
            $this->processDefaultEventBus($container);
        }
    }

    private function processDefaultEventBus(ContainerBuilder $container): void
    {
        /** @var array<int, list<string>> $groupedProcessors */
        $groupedProcessors = [];

        /** @var list<array{priority: ?int}> $tags */
        foreach ($container->findTaggedServiceIds('event_sourcing.processor') as $id => $tags) {
            foreach ($tags as $attributes) {
                $priority = $attributes['priority'] ?? 0;
                $groupedProcessors[$priority][] = $id;
            }
        }

        krsort($groupedProcessors);

        $listeners = [];

        foreach ($groupedProcessors as $processors) {
            foreach ($processors as $id) {
                $listeners[] = new Reference($id);
            }
        }

        $eventBus = $container->getDefinition(ListenerProvider::class);
        $eventBus->setArgument(0, $listeners);
    }
}
