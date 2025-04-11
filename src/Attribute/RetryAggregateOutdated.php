<?php

namespace Patchlevel\EventSourcingBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class RetryAggregateOutdated
{
    public function __construct(
        public readonly int $maxRetries = 3,
    ) {
    }
}