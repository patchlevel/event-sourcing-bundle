<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcingBundle\CacheWarmer\MetadataCacheWarmer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_keys;
use function array_unique;
use function array_values;
use function class_exists;
use function is_string;

/**
 * Passes the subscriber classes to the warmer, so that the subscribers do not have to be instantiated.
 *
 * @internal
 */
final class MetadataCacheWarmerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(MetadataCacheWarmer::class)) {
            return;
        }

        $subscriberClasses = [];

        foreach (array_keys($container->findTaggedServiceIds('event_sourcing.subscriber')) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass());

            if (!is_string($class) || !class_exists($class)) {
                continue;
            }

            $subscriberClasses[] = $class;
        }

        $container->getDefinition(MetadataCacheWarmer::class)
            ->setArgument(7, array_values(array_unique($subscriberClasses)));
    }
}
