<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;

class CreatedSubscriber extends Subscriber
{
    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        // do nothing
    }
}
