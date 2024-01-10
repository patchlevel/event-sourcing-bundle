<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Listener;

use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Lock\LockFactory;

final class ProjectionistAutoTeardownListener
{
    public function __construct(
        private readonly Projectionist $projectionist,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $lock = $this->lockFactory->createLock('projectionist-teardown');

        if (!$lock->acquire()) {
            return;
        }

        try {
            $this->projectionist->teardown();
        } finally {
            $lock->release();
        }
    }
}
