<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Listener;

use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Lock\LockFactory;

final class ProjectionistAutoRecoveryListener
{
    public function __construct(
        private readonly Projectionist $projectionist,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $lock = $this->lockFactory->createLock('projectionist-recovery');

        if (!$lock->acquire()) {
            return;
        }

        try {
            $projections = $this->projectionist->projections();

            $hasError = false;

            foreach ($projections as $projection) {
                if ($projection->isError()) {
                    $hasError = true;
                    break;
                }
            }

            if (!$hasError) {
                return;
            }

            $this->projectionist->remove();
        } finally {
            $lock->release();
        }
    }
}
