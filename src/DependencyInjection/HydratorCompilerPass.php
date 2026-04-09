<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\Hydrator\StackHydratorBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function array_keys;

/** @internal */
final class HydratorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(StackHydratorBuilder::class)) {
            return;
        }

        $builder = $container->getDefinition(StackHydratorBuilder::class);
        $extensions = $container->findTaggedServiceIds('event_sourcing.hydrator.extension');

        foreach (array_keys($extensions) as $subscriberServiceName) {
            $builder->addMethodCall('useExtension', [new Reference($subscriberServiceName)]);
        }
    }
}
