<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Patchlevel\EventSourcingBundle\Normalizer\DatePointNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\DatePoint;

#[CoversClass(DatePointNormalizer::class)]
final class DatePointNormalizerTest extends TestCase
{
    public function testNormalizeWithNull(): void
    {
        $normalizer = new DatePointNormalizer();

        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new DatePointNormalizer();

        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('type "Symfony\Component\Clock\DatePoint|null" was expected but "string" was passed.');

        $normalizer = new DatePointNormalizer();
        $normalizer->normalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new DatePointNormalizer();

        self::assertEquals(
            $normalizer->normalize(DatePoint::createFromFormat(DatePoint::ATOM, '2024-06-01T12:00:00+00:00')),
            '2024-06-01T12:00:00+00:00',
        );
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new DatePointNormalizer();

        $this->assertEquals(
            DatePoint::createFromFormat(DatePoint::ATOM, '2024-06-01T12:00:00+00:00'),
            $normalizer->denormalize('2024-06-01T12:00:00+00:00'),
        );
    }
}
