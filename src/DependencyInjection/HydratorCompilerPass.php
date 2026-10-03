<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function array_keys;

/** @internal */
final class HydratorCompilerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(StackHydratorBuilder::class)) {
            return;
        }

        $upcasters = $this->findAndSortTaggedServices('event_sourcing.upcaster', $container);

        if ($upcasters !== []) {
            $container->register(UpcastExtension::class, UpcastExtension::class)
                ->setArguments([[], $upcasters])
                ->addTag('event_sourcing.hydrator.extension');
        }

        $builder = $container->getDefinition(StackHydratorBuilder::class);
        $extensions = $container->findTaggedServiceIds('event_sourcing.hydrator.extension');

        foreach (array_keys($extensions) as $subscriberServiceName) {
            $builder->addMethodCall('useExtension', [new Reference($subscriberServiceName)]);
        }
    }
}
