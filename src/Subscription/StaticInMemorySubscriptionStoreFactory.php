<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Subscription;

use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;

final class StaticInMemorySubscriptionStoreFactory
{
    private static InMemorySubscriptionStore|null $store = null;

    public static function create(): InMemorySubscriptionStore
    {
        if (self::$store === null) {
            self::$store = new InMemorySubscriptionStore();
        }

        return self::$store;
    }
}
