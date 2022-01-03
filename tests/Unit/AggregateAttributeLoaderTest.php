<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcingBundle\Attribute\Aggregate;
use Patchlevel\EventSourcingBundle\Loader\AggregateAttributesLoader;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class AggregateAttributeLoaderTest extends TestCase
{
    public function testInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);

        $loader = new AggregateAttributesLoader();
        $loader->load([
            __DIR__ . '/../Fixtures/AttributedAggregatesSameName'
        ]);
    }

    public function testLoadPathWithAttributedAggregates()
    {
        $loader = new AggregateAttributesLoader();
        $result = $loader->load([
            __DIR__ . '/../Fixtures/AttributedAggregates'
        ]);

        self::assertCount(2, $result);
    }
}
