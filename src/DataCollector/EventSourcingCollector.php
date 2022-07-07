<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DataCollector;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Throwable;

use function array_map;

/**
 * @psalm-type MessageType = array{
 *     event_class: class-string,
 *     event_name: string,
 *     payload: string,
 *     aggregate_class: class-string<AggregateRoot>,
 *     aggregate_id: string,
 *     playhead: int,
 *     recorded_on: string,
 *     custom_headers: Data
 * }
 *
 * @psalm-type DataType = array{
 *    messages: list<MessageType>,
 *    aggregates: array<string, class-string<AggregateRoot>>,
 *    events: array<string, class-string>
 * }
 * @psalm-property DataType $data
 */
final class EventSourcingCollector extends DataCollector
{
    public function __construct(
        private readonly MessageListener $messageListener,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly EventRegistry $eventRegistry,
        private readonly EventSerializer $eventSerializer
    ) {
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $messages = array_map(
            function (Message $message) {
                $event = $message->event();

                $serializedEvent = $this->eventSerializer->serialize($event, [
                    Encoder::OPTION_PRETTY_PRINT => true
                ]);

                return [
                    'event_class' => $event::class,
                    'event_name' => $serializedEvent->name,
                    'payload' => $serializedEvent->payload,
                    'aggregate_class' => $message->aggregateClass(),
                    'aggregate_id' => $message->aggregateId(),
                    'playhead' => $message->playhead(),
                    'recorded_on' => $message->recordedOn()->format(DateTimeImmutable::ATOM),
                    'custom_headers' => $this->cloneVar($message->customHeaders()),
                ];
            },
            $this->messageListener->get()
        );

        $this->data = [
            'messages' => $messages,
            'aggregates' => $this->aggregateRootRegistry->aggregateClasses(),
            'events' => $this->eventRegistry->eventClasses(),
        ];
    }

    /**
     * @return list<MessageType>
     */
    public function getMessages(): array
    {
        return $this->data['messages'];
    }

    /**
     * @return array<string, class-string>
     */
    public function getEvents(): array
    {
        return $this->data['events'];
    }

    /**
     * @return array<string, class-string<AggregateRoot>> $aggregates
     */
    public function getAggregates(): array
    {
        return $this->data['aggregates'];
    }

    public function getName(): string
    {
        return self::class;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
