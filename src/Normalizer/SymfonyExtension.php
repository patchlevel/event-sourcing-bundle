<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Normalizer;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

final class SymfonyExtension implements Extension
{
    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addGuesser(new SymfonyGuesser(), -32);
    }
}
