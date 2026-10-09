<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\CacheWarmer;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

use function array_filter;
use function array_unique;
use function array_values;
use function is_file;

/**
 * Collects the hydrator metadata of the events, headers and aggregates with snapshots with the uncached factories
 * and dumps them into the php file that is read by the hydrator metadata cache.
 *
 * @internal
 */
final class HydratorMetadataCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly StackHydratorBuilder $hydratorBuilder,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly EventRegistry $eventRegistry,
        private readonly AggregateRootMetadataFactory $aggregateRootMetadataFactory,
        private readonly MessageHeaderRegistry $messageHeaderRegistry,
        private readonly string $phpArrayFile,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    /** @return array<string> classes to preload */
    public function warmUp(string $cacheDir, string|null $buildDir = null): array
    {
        // the file lives in the build dir, so it is already up to date if it exists
        if (is_file($this->phpArrayFile)) {
            return [];
        }

        // without deep cloning, the array adapter keeps the objects as they are, which can then be exported
        $cache = new ArrayAdapter(0, false);

        $hydrator = (clone $this->hydratorBuilder)->setCache($cache)->build();

        // aggregates are only hydrated for snapshots, events and headers are hydrated when they are (de)serialized
        $hydratedClasses = array_values($this->messageHeaderRegistry->headerClasses());

        foreach ($this->aggregateRootRegistry->aggregateClasses() as $aggregateClass) {
            if ($this->aggregateRootMetadataFactory->metadata($aggregateClass)->snapshot === null) {
                continue;
            }

            $hydratedClasses[] = $aggregateClass;
        }

        foreach ($this->eventRegistry->eventClasses() as $eventClass) {
            $hydratedClasses[] = $eventClass;
        }

        foreach (array_unique($hydratedClasses) as $hydratedClass) {
            $hydrator->metadata($hydratedClass);
        }

        $values = array_filter($cache->getValues(), static fn (mixed $value): bool => $value !== null);

        return (new PhpArrayAdapter($this->phpArrayFile, new NullAdapter()))->warmUp($values);
    }
}
