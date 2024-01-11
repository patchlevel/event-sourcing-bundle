<?php

namespace Patchlevel\EventSourcingBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsProcessor
{
    public function __construct(
        public int $priority = 0,
    ) {
    }
}