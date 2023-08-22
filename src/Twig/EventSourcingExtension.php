<?php

namespace Patchlevel\EventSourcingBundle\Twig;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EventSourcingExtension extends AbstractExtension
{
    public function __construct(
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly EventRegistry         $eventRegistry,
        private readonly EventSerializer       $eventSerializer,
    )
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('eventsourcing_aggregate_name', $this->aggregateName(...)),
            new TwigFunction('eventsourcing_event_class', $this->eventClass(...)),
            new TwigFunction('eventsourcing_event_name', $this->eventName(...)),
            new TwigFunction('eventsourcing_event_payload', $this->eventPayload(...)),
        ];
    }

    /**
     * @return class-string
     */
    public function aggregateName(Message $message): string
    {
        return $this->aggregateRootRegistry->aggregateName($message->aggregateClass());
    }

    /**
     * @return class-string
     */
    public function eventClass(Message $message): string
    {
        return get_class($message->event());
    }

    public function eventName(Message $message): string
    {
        return $this->eventRegistry->eventName(
            $this->eventClass($message)
        );
    }

    public function eventPayload(Message $message): string
    {
        return $this->eventSerializer->serialize(
            $message->event(),
            [
                JsonEncoder::OPTION_PRETTY_PRINT => true,
            ]
        )->payload;
    }
}
