<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\TypeAwareNormalizer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Uuid;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class UidNormalizer implements Normalizer, TypeAwareNormalizer
{
    public function __construct(
        /** @var class-string<AbstractUid>|null */
        private string|null $uidClass = null,
    ) {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof AbstractUid) {
            throw InvalidArgument::withWrongType(AbstractUid::class . '|null', $value);
        }

        return (string)$value;
    }

    public function denormalize(mixed $value): Uuid|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string|null', $value);
        }

        return Uuid::fromString($value);
    }

    public function handleType(Type|null $type): void
    {
        if ($type === null || $this->uidClass !== null) {
            return;
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof GenericType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof TemplateType) {
            $type = $type->getWrappedType();
        }

        if (!$type instanceof ObjectType) {
            return;
        }

        $this->uidClass = $type->getClassName();
    }

    public function getClassName(): string
    {
        if ($this->uidClass === null) {
            throw InvalidType::missingType();
        }

        return $this->uidClass;
    }
}
