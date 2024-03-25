<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\EventBus;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Message\Message;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final class SymfonyEventBus implements EventBus
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    /** @param Message<object> ...$messages */
    public function dispatch(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $envelope = (new Envelope($message))
                ->with(new DispatchAfterCurrentBusStamp());

            $this->bus->dispatch($envelope);
        }
    }
}
