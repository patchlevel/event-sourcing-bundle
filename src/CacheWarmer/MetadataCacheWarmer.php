<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\CacheWarmer;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr6AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr6AggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr6EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr6EventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\Psr6SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

use function array_filter;
use function is_file;

/**
 * Collects the registries and metadata of the aggregates, events and subscribers with the uncached factories
 * and dumps them into the php file that is read by the metadata cache.
 *
 * @internal
 */
final class MetadataCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param list<string>       $aggregatePaths
     * @param list<string>       $eventPaths
     * @param list<class-string> $subscriberClasses
     */
    public function __construct(
        private readonly AggregateRootRegistryFactory $aggregateRootRegistryFactory,
        private readonly EventRegistryFactory $eventRegistryFactory,
        private readonly AggregateRootMetadataFactory $aggregateRootMetadataFactory,
        private readonly EventMetadataFactory $eventMetadataFactory,
        private readonly SubscriberMetadataFactory $subscriberMetadataFactory,
        private readonly array $aggregatePaths,
        private readonly array $eventPaths,
        private readonly array $subscriberClasses,
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

        $aggregateRootRegistry = (new Psr6AggregateRootRegistryFactory($this->aggregateRootRegistryFactory, $cache))
            ->create($this->aggregatePaths);
        $eventRegistry = (new Psr6EventRegistryFactory($this->eventRegistryFactory, $cache))
            ->create($this->eventPaths);

        $aggregateRootMetadataFactory = new Psr6AggregateRootMetadataFactory($this->aggregateRootMetadataFactory, $cache);
        $eventMetadataFactory = new Psr6EventMetadataFactory($this->eventMetadataFactory, $cache);
        $subscriberMetadataFactory = new Psr6SubscriberMetadataFactory($this->subscriberMetadataFactory, $cache);

        foreach ($aggregateRootRegistry->aggregateClasses() as $aggregateClass) {
            $aggregateRootMetadataFactory->metadata($aggregateClass);
        }

        foreach ($eventRegistry->eventClasses() as $eventClass) {
            $eventMetadataFactory->metadata($eventClass);
        }

        foreach ($this->subscriberClasses as $subscriberClass) {
            $subscriberMetadataFactory->metadata($subscriberClass);
        }

        $values = array_filter($cache->getValues(), static fn (mixed $value): bool => $value !== null);

        return (new PhpArrayAdapter($this->phpArrayFile, new NullAdapter()))->warmUp($values);
    }
}
