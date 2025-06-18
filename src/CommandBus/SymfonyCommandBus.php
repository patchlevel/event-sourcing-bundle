<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\CommandBus;

use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final class SymfonyCommandBus implements CommandBus
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function dispatch(object $command): void
    {
        try {
            $this->messageBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            throw $e->getWrappedExceptions(null, true)[0] ?? $e;
        }
    }
}
