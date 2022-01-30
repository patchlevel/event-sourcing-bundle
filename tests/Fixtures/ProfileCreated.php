<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

class ProfileCreated extends AggregateChanged
{
    public static function raise(string $id): static
    {
        return new static($id);
    }
}
