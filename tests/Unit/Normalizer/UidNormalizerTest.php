<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Patchlevel\EventSourcingBundle\Normalizer\UidNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(UidNormalizer::class)]
final class UidNormalizerTest extends TestCase
{
    public function testNormalizeWithNull(): void
    {
        $normalizer = new UidNormalizer(UuidV7::class);

        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new UidNormalizer(UuidV7::class);

        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('type "Symfony\Component\Uid\AbstractUid|null" was expected but "string" was passed.');

        $normalizer = new UidNormalizer(UuidV7::class);
        $normalizer->normalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new UidNormalizer(UuidV7::class);

        self::assertEquals(
            $normalizer->normalize(UuidV7::fromString('019d2984-ee8a-73a1-b9b6-3041d960ae9b')),
            '019d2984-ee8a-73a1-b9b6-3041d960ae9b',
        );
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new UidNormalizer(UuidV7::class);

        $this->assertEquals(
            UuidV7::fromString('019d2984-ee8a-73a1-b9b6-3041d960ae9b'),
            $normalizer->denormalize('019d2984-ee8a-73a1-b9b6-3041d960ae9b'),
        );
    }

    public function testAutoDetect(): void
    {
        $normalizer = new UidNormalizer();
        $normalizer->handleType(Type::object(UuidV7::class));

        self::assertEquals(UuidV7::class, $normalizer->getClassName());
    }

    public function testAutoDetectOverrideNotPossible(): void
    {
        $normalizer = new UidNormalizer(UuidV4::class);
        $normalizer->handleType(Type::object(UuidV7::class));

        self::assertEquals(UuidV4::class, $normalizer->getClassName());
    }

    public function testAutoDetectMissingType(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new UidNormalizer();

        $normalizer->getClassName();
    }

    public function testAutoDetectMissingTypeBecauseNull(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new UidNormalizer();
        $normalizer->handleType(null);

        $normalizer->getClassName();
    }

    public function testGeneric(): void
    {
        $normalizer = new UidNormalizer();
        $normalizer->handleType(Type::generic(Type::object(UuidV7::class)));

        self::assertEquals(UuidV7::class, $normalizer->getClassName());
    }

    public function testTemplate(): void
    {
        $normalizer = new UidNormalizer();
        $normalizer->handleType(Type::template('T', Type::object(UuidV7::class)));

        self::assertEquals(UuidV7::class, $normalizer->getClassName());
    }
}
