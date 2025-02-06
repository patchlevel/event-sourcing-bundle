<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Subscription;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

final readonly class ResetServicesListener
{
    public function __construct(private ServicesResetter $servicesResetter)
    {
    }

    public function onWorkerRunningEvent(WorkerRunningEvent $event): void
    {
        $this->servicesResetter->reset();
    }
}
