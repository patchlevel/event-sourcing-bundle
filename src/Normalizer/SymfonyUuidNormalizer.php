<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Symfony\Component\Uid\Uuid;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class SymfonyUuidNormalizer implements Normalizer
{
    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Uuid) {
            throw new InvalidArgument();
        }

        return (string)$value;
    }

    public function denormalize(mixed $value): Uuid|null
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
