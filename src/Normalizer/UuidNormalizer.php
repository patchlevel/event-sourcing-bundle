<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Normalizer;

use Patchlevel\EventSourcing\Serializer\Normalizer\InvalidArgument;
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;
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
            throw new InvalidArgument();
        }

        return (string)$value;
    }

    public function denormalize(mixed $value): ?Uuid
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgument();
        }

        return Uuid::fromString($value);
    }
}
