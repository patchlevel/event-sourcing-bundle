<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('profile.created')]
class ProfileCreated
{
    public function __construct(
        #[IdNormalizer(CustomId::class)]
        public CustomId $id
    ) {

    }
}
