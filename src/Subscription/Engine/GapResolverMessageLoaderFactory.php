<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Subscription\Engine;

use DateInterval;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use Psr\Clock\ClockInterface;

/** @internal */
final class GapResolverMessageLoaderFactory
{
    /** @param list<int> $retriesInMs in milliseconds */
    public static function create(
        Store $store,
        ClockInterface $clock,
        array $retriesInMs,
        string|null $detectionWindow,
    ): GapResolverStoreMessageLoader {
        return new GapResolverStoreMessageLoader(
            $store,
            $clock,
            $retriesInMs,
            $detectionWindow !== null ? new DateInterval($detectionWindow) : null,
        );
    }
}
