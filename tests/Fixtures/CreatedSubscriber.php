<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;

class CreatedSubscriber extends Subscriber
{
    #[Handle(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        // do nothing
    }
}
