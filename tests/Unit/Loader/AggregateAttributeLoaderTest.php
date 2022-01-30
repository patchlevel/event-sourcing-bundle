<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\Loader;

use InvalidArgumentException;
use Patchlevel\EventSourcingBundle\Loader\AggregateAttributesLoader;
use PHPUnit\Framework\TestCase;

class AggregateAttributeLoaderTest extends TestCase
{
    public function testDuplicateAggregateName()
    {
        $this->expectException(InvalidArgumentException::class);

        $loader = new AggregateAttributesLoader();
        $loader->load([
            __DIR__ . '/../../Fixtures/AttributedAggregatesSameName'
        ]);
    }

    public function testLoadPathWithAttributedAggregates()
    {
        $loader = new AggregateAttributesLoader();
        $result = $loader->load([
            __DIR__ . '/../../Fixtures/AttributedAggregates'
        ]);

        self::assertCount(2, $result);
    }
}
