<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Aggregate('snapshotable_profile')]
#[Snapshot('default')]
class SnapshotableProfile extends BasicAggregateRoot
{
    #[Id]
    #[IdNormalizer(CustomId::class)]
    private CustomId $id;

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        // do nothing
    }
}
