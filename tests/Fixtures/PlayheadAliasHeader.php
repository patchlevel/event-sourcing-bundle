<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Header;

#[Header('playheadAlias', aliases: ['playhead'])]
final class PlayheadAliasHeader
{
}
