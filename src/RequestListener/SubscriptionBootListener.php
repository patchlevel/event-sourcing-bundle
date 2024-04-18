<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\RequestListener;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Symfony\Component\HttpKernel\Event\KernelEvent;

final class SubscriptionBootListener
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param positive-int|null $limit
     */
    public function __construct(
        private readonly SubscriptionEngine $engine,
        private readonly array|null $ids = null,
        private readonly array|null $groups = null,
        private readonly int|null $limit = null,
    ) {
    }

    public function __invoke(KernelEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->engine->boot(
            new SubscriptionEngineCriteria(
                $this->ids,
                $this->groups,
            ),
            $this->limit,
        );
    }
}
