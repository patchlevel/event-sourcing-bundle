<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcingBundle\Attribute\AsProcessor;

#[AsProcessor]
class Processor1
{
    #[Subscribe(ProfileCreated::class)]
    public function __invoke(Message $message): void
    {
        // do nothing
    }
}
