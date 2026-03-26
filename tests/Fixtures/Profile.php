<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Repository\Repository;

#[Aggregate('profile')]
class Profile extends BasicAggregateRoot
{
    #[Id]
    private CustomId $id;

    /**  @param Repository<self> $profileRepository */
    #[Handle]
    public static function create(
        CreateProfile $command,
        Repository $profileRepository,
    ): self {
        $profile = new self();
        $profile->recordThat(new ProfileCreated($command->id));

        return $profile;
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->id;
    }
}
