<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DataCollector;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Message\Message;

class MessageCollectorEventBus implements EventBus
{
    /** @var list<Message<object>> */
    private array $messages = [];

    public function __construct(
        private EventBus|null $eventBus = null,
    ) {
    }

    /** @param Message<object> ...$messages */
    public function dispatch(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $this->messages[] = $message;
        }

        $this->eventBus?->dispatch(...$messages);
    }

    /** @return list<Message<object>> */
    public function get(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
