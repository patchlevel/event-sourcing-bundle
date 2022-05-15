<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

class Processor2 implements Listener
{
    public function __invoke(Message $message): void
    {
        // do nothing
    }
}
