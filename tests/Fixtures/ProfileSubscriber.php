<?php

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber('profile', RunMode::FromNow)]
class ProfileSubscriber
{
}
