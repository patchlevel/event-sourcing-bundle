<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\Attribute\Projector;

#[Projector('profile')]
class ProfileProjector
{
    #[Answer]
    public function query(QueryFoo $query): string
    {
        return $query->result;
    }
}
