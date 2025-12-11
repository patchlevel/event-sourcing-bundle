<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\QueryBus\HandlerFinder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_keys;

/** @internal */
final class QueryHandlerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('patchlevel_event_sourcing.query_handlers.bus')) {
            return;
        }

        $bus = ServiceAliasResolver::resolve(
            $container,
            $container->getParameter('patchlevel_event_sourcing.query_handlers.bus'),
        );
        $subscribers = $container->findTaggedServiceIds('event_sourcing.subscriber');

        foreach (array_keys($subscribers) as $subscriberServiceName) {
            $subscriberDefinition = $container->getDefinition($subscriberServiceName);
            $subscriberClass = $subscriberDefinition->getClass();

            if ($subscriberClass === null) {
                continue;
            }

            foreach (HandlerFinder::findInClass($subscriberClass) as $queryHandler) {
                $subscriberDefinition->addTag(
                    'messenger.message_handler',
                    [
                        'method' => $queryHandler->method,
                        'handles' => $queryHandler->queryClass,
                        'bus' => $bus,
                    ],
                );
            }
        }
    }
}
