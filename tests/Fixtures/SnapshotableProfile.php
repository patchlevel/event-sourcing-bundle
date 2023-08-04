<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('snapshotable_profile')]
#[Snapshot('default')]
class SnapshotableProfile extends BasicAggregateRoot
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
