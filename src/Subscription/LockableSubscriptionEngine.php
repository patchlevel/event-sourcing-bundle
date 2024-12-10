<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Subscription;

use Closure;
use Patchlevel\EventSourcing\Subscription\Engine\AlreadyProcessing;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Symfony\Component\Lock\LockFactory;

final class LockableSubscriptionEngine implements SubscriptionEngine
{
    public function __construct(
        private readonly SubscriptionEngine $subscriptionEngine,
        private readonly LockFactory $lockFactory,
        private readonly string $lockName = 'subscription-engine',
        private readonly bool $blocking = false,
    ) {
    }

    public function setup(SubscriptionEngineCriteria|null $criteria = null, bool $skipBooting = false): Result
    {
        return $this->inLock(
            fn () => $this->subscriptionEngine->setup($criteria, $skipBooting),
        );
    }

    public function boot(SubscriptionEngineCriteria|null $criteria = null, int|null $limit = null): ProcessedResult
    {
        return $this->inLock(
            fn () => $this->subscriptionEngine->boot($criteria, $limit),
        );
    }

    public function run(SubscriptionEngineCriteria|null $criteria = null, int|null $limit = null): ProcessedResult
    {
        return $this->inLock(
            fn () => $this->subscriptionEngine->run($criteria, $limit),
        );
    }

    public function teardown(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->inLock(
            fn () => $this->subscriptionEngine->teardown($criteria),
        );
    }

    public function remove(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->inLock(
            fn () => $this->subscriptionEngine->remove($criteria),
        );
    }

    public function reactivate(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->inLock(
            fn () => $this->subscriptionEngine->reactivate($criteria),
        );
    }

    public function pause(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->inLock(
            fn () => $this->subscriptionEngine->pause($criteria),
        );
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        return $this->subscriptionEngine->subscriptions($criteria);
    }

    /**
     * @param Closure(): T $callback
     *
     * @return T
     *
     * @template T
     */
    private function inLock(Closure $callback): mixed
    {
        $lock = $this->lockFactory->createLock($this->lockName);

        if (!$lock->acquire($this->blocking)) {
            throw new AlreadyProcessing();
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
