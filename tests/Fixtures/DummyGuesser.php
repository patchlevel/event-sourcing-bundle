<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Symfony\Component\TypeInfo\Type\ObjectType;

final readonly class DummyGuesser implements Guesser
{
    public function guess(ObjectType $type): Normalizer|null
    {
        return null;
    }
}
