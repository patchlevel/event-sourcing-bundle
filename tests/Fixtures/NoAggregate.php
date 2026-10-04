<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('no_aggregate')]
final class NoAggregate
{
}
