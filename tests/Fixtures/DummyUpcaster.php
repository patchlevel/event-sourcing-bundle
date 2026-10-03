<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Fixtures;

use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class DummyUpcaster implements Upcaster
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function upcast(ClassMetadata $metadata, array $data, array $context): array
    {
        return $data;
    }
}
