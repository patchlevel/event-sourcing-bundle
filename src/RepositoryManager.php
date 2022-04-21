<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_key_exists;

final class RepositoryManager
{
    private AggregateRootRegistry $aggregateRootRegistry;
    private Store $store;
    private EventBus $eventBus;
    private ?SnapshotStore $snapshotStore;
    private LoggerInterface $logger;

    /** @var array<class-string<AggregateRoot>, Repository> */
    private array $instances = [];

    public function __construct(
        AggregateRootRegistry $aggregateRootRegistry,
        Store $store,
        EventBus $eventBus,
        ?SnapshotStore $snapshotStore = null,
        ?LoggerInterface $logger = null
    ) {
        $this->aggregateRootRegistry = $aggregateRootRegistry;
        $this->store = $store;
        $this->eventBus = $eventBus;
        $this->snapshotStore = $snapshotStore;
        $this->logger = $logger ?? new NullLogger();
    }

    /** @param class-string<AggregateRoot> $aggregateClass */
    public function get(string $aggregateClass): Repository
    {
        if (array_key_exists($aggregateClass, $this->instances)) {
            return $this->instances[$aggregateClass];
        }

        if (!$this->aggregateRootRegistry->hasAggregateClass($aggregateClass)) {
            throw new AggregateRootClassNotRegistered($aggregateClass);
        }

        return $this->instances[$aggregateClass] = new DefaultRepository(
            $this->store,
            $this->eventBus,
            $aggregateClass,
            $this->snapshotStore,
            $this->logger
        );
    }
}
