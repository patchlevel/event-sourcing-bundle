<?php

namespace Patchlevel\EventSourcingBundle\CommandBus;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class AggregateOutdatedRetryStamp implements StampInterface
{
    public function __construct(
        public readonly int $retryCount,
        public readonly int $maxRetries,
    ) {
    }
}