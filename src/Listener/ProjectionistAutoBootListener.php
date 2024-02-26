<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Listener;

use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class ProjectionistAutoBootListener
{
    public function __construct(
        private readonly Projectionist $projectionist,
        private readonly ProjectionistCriteria|null $criteria = null,
        private readonly int|null $limit = null,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->projectionist->boot($this->criteria, $this->limit);
    }
}
