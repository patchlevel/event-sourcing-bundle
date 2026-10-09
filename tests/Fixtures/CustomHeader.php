<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\EventSourcing\Attribute\Header;

#[Header('custom', aliases: ['legacyCustom'])]
class CustomHeader
{
    public function __construct(
        readonly string $value,
    ) {
    }
}
