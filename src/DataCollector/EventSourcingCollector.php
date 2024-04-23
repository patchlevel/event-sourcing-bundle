<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DataCollector;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
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
 *     event: Data,
 *     headers: list<Data>
 * }
 * @psalm-type DataType = array{
 *    messages: list<MessageType>,
 *    aggregates: array<string, class-string<AggregateRoot>>,
 *    events: array<string, class-string>
 * }
 * @psalm-property DataType|array{} $data
 */
final class EventSourcingCollector extends DataCollector
{
    public function __construct(
        private readonly MessageCollectorEventBus $messageCollectorEventBus,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly EventRegistry $eventRegistry,
    ) {
    }

    public function collect(Request $request, Response $response, Throwable|null $exception = null): void
    {
        $messages = array_map(
            function (Message $message) {
                return [
                    'event_class' => $message->event()::class,
                    'event_name' => $this->eventRegistry->eventName($message->event()::class),
                    'event' => $this->cloneVar($message->event()),
                    'headers' => array_map(
                        fn (object $header) => $this->cloneVar($header),
                        $message->headers(),
                    ),
                ];
            },
            $this->messageCollectorEventBus->get(),
        );

        $this->data = [
            'messages' => $messages,
            'aggregates' => $this->aggregateRootRegistry->aggregateClasses(),
            'events' => $this->eventRegistry->eventClasses(),
        ];
    }

    /** @return list<MessageType> */
    public function getMessages(): array
    {
        return $this->data['messages'] ?? [];
    }

    /** @return array<string, class-string> */
    public function getEvents(): array
    {
        return $this->data['events'] ?? [];
    }

    /** @return array<string, class-string<AggregateRoot>> $aggregates */
    public function getAggregates(): array
    {
        return $this->data['aggregates'] ?? [];
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
