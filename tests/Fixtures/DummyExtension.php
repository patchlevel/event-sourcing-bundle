<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

final readonly class DummyExtension implements Extension
{
    public function configure(StackHydratorBuilder $builder): void
    {
        // do nothing
    }
}
