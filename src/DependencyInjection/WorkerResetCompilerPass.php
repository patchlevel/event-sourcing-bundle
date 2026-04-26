<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcingBundle\Subscription\ResetServicesListener;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

/**
 * Builds a worker-specific ServicesResetter that excludes the event dispatcher.
 *
 * In debug mode, Symfony decorates the event dispatcher with TraceableEventDispatcher
 * and tags it with kernel.reset. When the ResetServicesListener fires during
 * WorkerRunningEvent dispatch, the global services_resetter calls reset() on the
 * TraceableEventDispatcher — clearing its internal state (dispatchDepth) while the
 * event is still being dispatched. This causes an "Undefined array key" warning
 * in postProcess() (symfony/event-dispatcher >= 8.0.8).
 *
 * This pass creates a separate ServicesResetter for the worker that excludes the
 * debug.event_dispatcher, so the global services_resetter remains unchanged for
 * HTTP request resets.
 */
final class WorkerResetCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ResetServicesListener::class)) {
            return;
        }

        if (!$container->hasDefinition('debug.event_dispatcher')) {
            return;
        }

        $services = [];
        /** @var array<string, list<string>> $methods */
        $methods = [];

        foreach ($container->findTaggedServiceIds('kernel.reset', true) as $id => $tags) {
            if ($id === 'debug.event_dispatcher') {
                continue;
            }

            $services[$id] = new Reference($id, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE);

            foreach ($tags as $attributes) {
                /** @var array{method?: string, on_invalid?: string} $attributes */
                if (!isset($attributes['method'])) {
                    continue;
                }

                $methods[$id] ??= [];

                $method = $attributes['method'];

                if (($attributes['on_invalid'] ?? null) === 'ignore') {
                    $method = '?' . $method;
                }

                $methods[$id][] = $method;
            }
        }

        if ($services === []) {
            return;
        }

        $container->register('patchlevel.worker.services_resetter', ServicesResetter::class)
            ->setArguments([
                new IteratorArgument($services),
                $methods,
            ]);

        $container->getDefinition(ResetServicesListener::class)
            ->setArgument(0, new Reference('patchlevel.worker.services_resetter'));
    }
}
