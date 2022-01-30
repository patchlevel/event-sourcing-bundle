<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Loader;

use RuntimeException;

use function sprintf;

class DuplicateAggregateDefinition extends RuntimeException
{
    /**
     * @param class-string $oldClass
     * @param class-string $newClass
     */
    public function __construct(string $aggregateName, string $oldClass, string $newClass)
    {
        parent::__construct(
            sprintf(
                'found duplicate aggregate name "%s", it was found in class "%s" and "%s"',
                $aggregateName,
                $oldClass,
                $newClass
            )
        );
    }
}
