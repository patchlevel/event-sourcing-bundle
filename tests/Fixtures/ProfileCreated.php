<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.created')]
class ProfileCreated
{
    public function __construct(
        public string $id
    ) {

    }
}
