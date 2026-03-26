<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\Normalizer;

use Patchlevel\EventSourcingBundle\Normalizer\DatePointNormalizer;
use Patchlevel\EventSourcingBundle\Normalizer\SymfonyGuesser;
use Patchlevel\EventSourcingBundle\Normalizer\UidNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(SymfonyGuesser::class)]
final class SymfonyGuesserTest extends TestCase
{
    /** @param class-string $typeClass */
    #[DataProvider('successfulGuessProvider')]
    public function testGuessSuccessful(string $typeClass, Normalizer $expectedNormalizer): void
    {
        $guesser = new SymfonyGuesser();

        $normalizer = $guesser->guess(new ObjectType($typeClass));

        self::assertEquals($expectedNormalizer, $normalizer);
    }

    /** @return iterable<array{class-string, Normalizer}> */
    public static function successfulGuessProvider(): iterable
    {
        yield [Uuid::class, new UidNormalizer(Uuid::class)];
        yield [UuidV4::class, new UidNormalizer(UuidV4::class)];
        yield [UuidV7::class, new UidNormalizer(UuidV7::class)];
        yield [Ulid::class, new UidNormalizer(Ulid::class)];
        yield [DatePoint::class, new DatePointNormalizer()];
    }

    public function testGuessReturnsNullForUnknownType(): void
    {
        $guesser = new SymfonyGuesser();

        $normalizer = $guesser->guess(new ObjectType(stdClass::class));

        self::assertNull($normalizer);
    }
}
