<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcingBundle\Attribute\AsListener;

#[AsListener]
class ProfileListener
{
}
