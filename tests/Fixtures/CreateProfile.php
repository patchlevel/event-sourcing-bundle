<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Identifier\CustomId;

class CreateProfile
{
    public function __construct(
        public readonly CustomId $id,
    ) {

    }
}