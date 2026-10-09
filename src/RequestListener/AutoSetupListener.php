<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\RequestListener;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Status;
use Symfony\Component\HttpKernel\Event\RequestEvent;

use function preg_match;

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
        private readonly string|null $excludeUrl = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (
            $this->excludeUrl !== null
            && preg_match('#' . $this->excludeUrl . '#', $event->getRequest()->getRequestUri())
        ) {
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

        $this->subscriptionEngine->execute(
            new Setup(
                ids: $ids,
                skipBooting: true,
            ),
        );
    }
}
