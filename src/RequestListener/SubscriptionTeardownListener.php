<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\RequestListener;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Symfony\Component\HttpKernel\Event\KernelEvent;

final class SubscriptionTeardownListener
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     */
    public function __construct(
        private readonly SubscriptionEngine $engine,
        private readonly array|null $ids = null,
        private readonly array|null $groups = null,
    ) {
    }

    public function __invoke(KernelEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->engine->teardown(
            new SubscriptionEngineCriteria(
                $this->ids,
                $this->groups,
            ),
        );
    }
}
