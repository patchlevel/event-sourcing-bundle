<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Messenger;

use Patchlevel\EventSourcing\Repository\MessageDecorator\Trace;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

/** @experimental */
final class TraceHandlersLocator implements HandlersLocatorInterface
{
    public function __construct(
        private readonly HandlersLocatorInterface $parent,
        private readonly TraceStack $traceStack,
    ) {
    }

    /** @return iterable<int, HandlerDescriptor> */
    public function getHandlers(Envelope $envelope): iterable
    {
        $busName = $envelope->last(BusNameStamp::class)->getBusName();

        foreach ($this->parent->getHandlers($envelope) as $handler) {
            $this->traceStack->add(new Trace(
                $handler->getName(),
                'symfony/messenger/' . $busName,
            ));

            yield $handler;

            $this->traceStack->remove(new Trace(
                $handler->getName(),
                'symfony/messenger/' . $busName,
            ));
        }
    }
}
