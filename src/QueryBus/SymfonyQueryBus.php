<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\QueryBus;

use Patchlevel\EventSourcing\QueryBus\QueryBus;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class SymfonyQueryBus implements QueryBus
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function dispatch(object $query): mixed
    {
        $handledStamp = $this->messageBus->dispatch($query)->last(HandledStamp::class);

        if ($handledStamp === null) {
            throw new RuntimeException('No message handled yet');
        }

        return $handledStamp->getResult();
    }
}
