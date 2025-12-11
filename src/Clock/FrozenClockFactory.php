<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Clock;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\FrozenClock;

/** @internal */
final class FrozenClockFactory
{
    public static function create(string $dateTimeString): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable($dateTimeString));
    }
}
