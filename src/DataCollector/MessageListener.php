<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DataCollector;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;

final class MessageListener
{
    /** @var list<Message<object>> */
    private array $messages = [];

    /** @param Message<object> $message */
    #[Subscribe('*')]
    public function __invoke(Message $message): void
    {
        $this->messages[] = $message;
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
