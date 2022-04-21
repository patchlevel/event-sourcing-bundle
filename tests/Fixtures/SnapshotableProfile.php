<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('snapshotable_profile')]
#[Snapshot('default')]
class SnapshotableProfile extends SnapshotableAggregateRoot
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

    protected function serialize(): array
    {
        return [];
    }

    protected static function deserialize(array $payload): static
    {
        return new self();
    }
}
