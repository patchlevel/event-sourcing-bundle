<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Subscription;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Symfony\Contracts\Service\ResetInterface;

final readonly class ResetServicesListener
{
    public function __construct(private ResetInterface $servicesResetter)
    {
    }

    public function onWorkerRunningEvent(WorkerRunningEvent $event): void
    {
        $this->servicesResetter->reset();
    }
}
