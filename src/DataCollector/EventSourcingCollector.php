<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DataCollector;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Throwable;

use function array_map;

/**
 * @psalm-type DataType = array{
 *    messages: list<Data>,
 *    aggregates: array<string, class-string<AggregateRoot>>,
 *    events: array<string, class-string>
 * }
 * @psalm-property DataType $data
 */
final class EventSourcingCollector extends DataCollector
{
    private MessageListener $messageListener;
    private AggregateRootRegistry $aggregateRootRegistry;
    private EventRegistry $eventRegistry;

    public function __construct(MessageListener $messageListener, AggregateRootRegistry $aggregateRootRegistry, EventRegistry $eventRegistry)
    {
        $this->messageListener = $messageListener;
        $this->aggregateRootRegistry = $aggregateRootRegistry;
        $this->eventRegistry = $eventRegistry;
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $messages = array_map(
            function (Message $message) {
                return $this->cloneVar($message);
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
     * @return list<Data>
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
