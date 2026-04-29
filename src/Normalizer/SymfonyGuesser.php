<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Normalizer;

use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\Uid\AbstractUid;

final class SymfonyGuesser implements Guesser
{
    public function guess(ObjectType $type): Normalizer|null
    {
        if ($type->isIdentifiedBy(AbstractUid::class)) {
            return new UidNormalizer($type->getClassName());
        }

        if ($type->isIdentifiedBy(DatePoint::class)) {
            return new DatePointNormalizer();
        }

        return null;
    }
}
