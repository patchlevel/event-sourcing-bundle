<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Listener;

use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Lock\LockFactory;

final class ProjectionistAutoBootListener
{
    public function __construct(
        private readonly Projectionist $projectionist,
        private readonly LockFactory $lockFactory,
        private readonly bool $throwByError = true,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $lock = $this->lockFactory->createLock('projectionist-boot');

        if (!$lock->acquire()) {
            return;
        }

        try {
            $this->projectionist->boot(throwByError: $this->throwByError);
        } finally {
            $lock->release();
        }
    }
}
