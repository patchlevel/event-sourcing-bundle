<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DataCollector;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;

class EventListener implements Listener
{
    /** @var list<AggregateChanged<array<string, mixed>>> */
    private array $events = [];

    public function __invoke(AggregateChanged $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<AggregateChanged<array<string, mixed>>>
     */
    public function get(): array
    {
        return $this->events;
    }

    public function clear(): void
    {
        $this->events = [];
    }
}
