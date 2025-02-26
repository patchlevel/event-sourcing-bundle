<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

final readonly class QueryFoo
{
    public function __construct(public string $result)
    {
    }
}