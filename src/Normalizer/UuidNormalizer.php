<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Normalizer;

use Patchlevel\EventSourcing\Serializer\Normalizer;
use Symfony\Component\Uid\Uuid;

use function is_string;

class UuidNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Uuid) {
            throw new InvalidArgumentException();
        }

        return (string)$value;
    }

    public function denormalize(mixed $value): ?Uuid
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException();
        }

        return Uuid::fromString($value);
    }
}
