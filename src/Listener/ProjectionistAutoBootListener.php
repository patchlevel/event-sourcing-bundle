<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Listener;

use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class ProjectionistAutoBootListener
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param positive-int|null $limit
     */
    public function __construct(
        private readonly Projectionist $projectionist,
        private readonly array|null $ids = null,
        private readonly array|null $groups = null,
        private readonly int|null $limit = null,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->projectionist->boot(
            new ProjectionistCriteria(
                $this->ids,
                $this->groups,
            ),
            $this->limit
        );
    }
}
