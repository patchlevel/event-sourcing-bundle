<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack;
use Patchlevel\EventSourcingBundle\Messenger\TraceHandlersLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Handler\HandlersLocator;

/** @experimental */
final class TraceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(TraceStack::class)) {
            return;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if ($class !== HandlersLocator::class) {
                continue;
            }

            $container->register('event_sourcing.' . $id . '.trace_decorator', TraceHandlersLocator::class)
                ->setDecoratedService($id)
                ->setArguments([
                    new Reference('event_sourcing.' . $id . '.trace_decorator.inner'),
                    new Reference(TraceStack::class),
                ]);
        }
    }
}
