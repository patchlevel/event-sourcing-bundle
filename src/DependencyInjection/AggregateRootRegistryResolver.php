<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal */
final class AggregateRootRegistryResolver
{
    /**
     * The registry is created from the configured paths instead of the service,
     * because the service may depend on the metadata cache pool, which is not available while compiling.
     */
    public static function resolve(ContainerBuilder $container): AggregateRootRegistry|null
    {
        if (!$container->hasDefinition(AggregateRootRegistry::class)) {
            return null;
        }

        /** @var list<string> $paths */
        $paths = $container->getParameterBag()->resolveValue(
            $container->getDefinition(AggregateRootRegistry::class)->getArgument(0),
        );

        return (new AttributeAggregateRootRegistryFactory())->create($paths);
    }
}
