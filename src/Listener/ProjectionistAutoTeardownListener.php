<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Listener;

use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class ProjectionistAutoTeardownListener
{
    public function __construct(
        private readonly Projectionist $projectionist,
        private readonly ProjectionistCriteria|null $criteria = null,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->projectionist->teardown($this->criteria);
    }
}
