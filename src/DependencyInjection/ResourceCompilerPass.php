<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal */
final class ResourceCompilerPass implements CompilerPassInterface
{
    private const RESSOURCES = [
        'event_sourcing.aggregate' => 'event_sourcing.aggregates',
        'event_sourcing.event' => 'event_sourcing.events',
        'event_sourcing.header' => 'event_sourcing.headers',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::RESSOURCES as $tag => $parameter) {
            $map = [];
            foreach ($container->findTaggedResourceIds($tag) as $id => $tags) {
                foreach ($tags as $tag) {
                    $map[$tag['name']] = $id;
                }
            }

            $container->setParameter($parameter, $map);
        }
    }
}
