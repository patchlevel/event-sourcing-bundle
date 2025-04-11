<?php

namespace Patchlevel\EventSourcingBundle\CommandBus;

use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcingBundle\Attribute\RetryAggregateOutdated;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class AggregateOutdatedRetryMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (AggregateOutdated|HandlerFailedException $e) {
            if ($e instanceof HandlerFailedException) {
                $exceptions = $e->getWrappedExceptions(AggregateOutdated::class);

                if ($exceptions === []) {
                    throw $e;
                }
            }

            $retryStamp = $envelope->last(AggregateOutdatedRetryStamp::class);

            if ($retryStamp) {
                $currentRetries = $retryStamp->retryCount;
                $maxRetries = $retryStamp->maxRetries;
            } else {
                $currentRetries = 0;
                $maxRetries = $this->maxRetries($envelope);
            }

            if ($maxRetries <= $currentRetries) {
                throw $e;
            }

            $envelope->with(new AggregateOutdatedRetryStamp($currentRetries + 1, $maxRetries));

            return $this->handle($envelope, $stack);
        }
    }

    private function getMessage(Envelope $envelope): object
    {
        $message = $envelope->getMessage();

        if ($message instanceof Envelope) {
            return $this->getMessage($message);
        }

        return $message;
    }

    private function maxRetries(Envelope $envelope): int
    {
        $message = $this->getMessage($envelope);

        $reflectionClass = new ReflectionClass($message);
        $attributes = $reflectionClass->getAttributes(RetryAggregateOutdated::class);

        if ($attributes === []) {
            return 0;
        }

        return $attributes[0]->newInstance()->maxRetries;
    }
}