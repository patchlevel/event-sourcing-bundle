<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal */
final class ResourceCompilerPass implements CompilerPassInterface
{
    private const RESOURCES = [
        'event_sourcing.aggregate' => 'event_sourcing.aggregates',
        'event_sourcing.event' => 'event_sourcing.events',
        'event_sourcing.header' => 'event_sourcing.headers',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::RESOURCES as $tag => $parameter) {
            $map = [];

            /** @var array<string, list<array{name: string}>> $taggedResources */
            $taggedResources = $container->findTaggedResourceIds($tag);

            foreach ($taggedResources as $id => $attributes) {
                $class = $container->getDefinition($id)->getClass() ?? $id;

                foreach ($attributes as $attribute) {
                    $map[$attribute['name']] = $class;
                }
            }

            $container->setParameter($parameter, $map);
        }
    }
}
