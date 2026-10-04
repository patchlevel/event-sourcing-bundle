<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootAlreadyInRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\NoAggregateRoot;
use Patchlevel\EventSourcing\Metadata\Event\EventAlreadyInRegistry;
use Patchlevel\EventSourcing\Metadata\Message\HeaderAlreadyInRegistry;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_key_exists;
use function is_subclass_of;

/** @internal */
final class ResourceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $aggregates = [];

        foreach ($this->taggedClasses($container, 'event_sourcing.aggregate') as [$class, $attribute]) {
            if (!is_subclass_of($class, AggregateRoot::class)) {
                throw new NoAggregateRoot($class);
            }

            if (array_key_exists($attribute['name'], $aggregates)) {
                throw new AggregateRootAlreadyInRegistry($attribute['name']);
            }

            $aggregates[$attribute['name']] = $class;
        }

        $events = [];

        foreach ($this->taggedClasses($container, 'event_sourcing.event') as [$class, $attribute]) {
            foreach ([$attribute['name'], ...$attribute['aliases'] ?? []] as $name) {
                if (array_key_exists($name, $events)) {
                    throw new EventAlreadyInRegistry($name);
                }

                $events[$name] = $class;
            }
        }

        $headers = [];
        $headerAliases = [];

        foreach ($this->taggedClasses($container, 'event_sourcing.header') as [$class, $attribute]) {
            $headers[$attribute['name']] = $class;

            foreach ($attribute['aliases'] ?? [] as $alias) {
                if (array_key_exists($alias, $headerAliases)) {
                    throw new HeaderAlreadyInRegistry($alias);
                }

                $headerAliases[$alias] = $class;
            }
        }

        $headers = MessageHeaderRegistry::createWithInternalHeaders($headers)->headerClasses();

        foreach ($headerAliases as $alias => $class) {
            if (array_key_exists($alias, $headers)) {
                throw new HeaderAlreadyInRegistry($alias);
            }

            $headers[$alias] = $class;
        }

        $container->setParameter('event_sourcing.aggregates', $aggregates);
        $container->setParameter('event_sourcing.events', $events);
        $container->setParameter('event_sourcing.headers', $headers);
    }

    /** @return iterable<array{class-string, array{name: string, aliases?: list<string>}}> */
    private function taggedClasses(ContainerBuilder $container, string $tag): iterable
    {
        /** @var array<string, list<array{name: string, aliases?: list<string>}>> $taggedResources */
        $taggedResources = $container->findTaggedResourceIds($tag);

        foreach ($taggedResources as $id => $attributes) {
            /** @var class-string $class */
            $class = $container->getDefinition($id)->getClass() ?? $id;

            foreach ($attributes as $attribute) {
                yield [$class, $attribute];
            }
        }
    }
}
