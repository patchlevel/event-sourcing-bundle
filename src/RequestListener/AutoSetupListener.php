<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\RequestListener;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Status;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final class AutoSetupListener
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     */
    public function __construct(
        private readonly SubscriptionEngine $subscriptionEngine,
        private readonly array|null $ids,
        private readonly array|null $groups,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $subscriptions = $this->subscriptionEngine->subscriptions(
            new SubscriptionEngineCriteria(
                $this->ids,
                $this->groups,
            ),
        );

        $ids = [];

        foreach ($subscriptions as $subscription) {
            if ($subscription->status() !== Status::New) {
                continue;
            }

            $ids[] = $subscription->id();
        }

        $this->subscriptionEngine->setup(
            new SubscriptionEngineCriteria($ids),
            true,
        );
    }
}
