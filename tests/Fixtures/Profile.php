<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('profile')]
class Profile extends BasicAggregateRoot
{
    public function aggregateRootId(): string
    {
        return '1';
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        // do nothing
    }
}
