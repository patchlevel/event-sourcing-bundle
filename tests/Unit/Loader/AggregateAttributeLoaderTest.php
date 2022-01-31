<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\Loader;

use Patchlevel\EventSourcingBundle\Loader\AggregateAttributesLoader;
use Patchlevel\EventSourcingBundle\Loader\DuplicateAggregateDefinition;
use PHPUnit\Framework\TestCase;

class AggregateAttributeLoaderTest extends TestCase
{
    public function testDuplicateAggregateName(): void
    {
        $this->expectException(DuplicateAggregateDefinition::class);

        $loader = new AggregateAttributesLoader();
        $loader->load([
            __DIR__ . '/../../Fixtures/AttributedAggregatesSameName'
        ]);
    }

    public function testLoadPathWithAttributedAggregates(): void
    {
        $loader = new AggregateAttributesLoader();
        $result = $loader->load([
            __DIR__ . '/../../Fixtures/AttributedAggregates'
        ]);

        self::assertCount(2, $result);
    }
}
