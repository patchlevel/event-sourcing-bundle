<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

class Profile extends AggregateRoot
{
    public function aggregateRootId(): string
    {
        return '1';
    }
}
