<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Symfony\Component\Clock\DatePoint;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class DatePointNormalizer implements Normalizer
{
    public function __construct(
        private string $format = DatePoint::ATOM,
    ) {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DatePoint) {
            throw InvalidArgument::withWrongType(DatePoint::class . '|null', $value);
        }

        return $value->format($this->format);
    }

    public function denormalize(mixed $value): DatePoint|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string|null', $value);
        }

        return DatePoint::createFromFormat($this->format, $value);
    }
}
