<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DataCollector;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EventCollector extends AbstractDataCollector
{
    private EventListener $eventListener;

    public function __construct(EventListener $eventListener)
    {
        $this->eventListener = $eventListener;
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $events = $this->eventListener->get();

        $this->data = ['events' => $events];
    }

    public static function getTemplate(): string
    {
        return '@PatchlevelEventSourcing/Collector/template.html.twig';
    }

    /**
     * @return list<AggregateChanged<array<string, mixed>>>
     */
    public function getEvents(): array
    {
        return $this->data['events'];
    }
}
