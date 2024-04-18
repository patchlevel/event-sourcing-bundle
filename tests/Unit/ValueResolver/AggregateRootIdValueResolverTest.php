<?php

namespace Patchlevel\EventSourcingBundle\Tests\Unit\ValueResolver;

use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcingBundle\ValueResolver\AggregateRootIdValueResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @covers \Patchlevel\EventSourcingBundle\ValueResolver\AggregateRootIdValueResolver
 */
final class AggregateRootIdValueResolverTest extends TestCase
{
    public function testResolveValue(): void
    {
        $valueResolver = new AggregateRootIdValueResolver();

        $request = new Request();
        $request->attributes->set('id', '1');

        $argument = new ArgumentMetadata(
            'id',
            CustomId::class,
            false,
            false,
            null
        );

        $result = $valueResolver->resolve($request, $argument);

        self::assertEquals([new CustomId('1')], $result);
    }

    public function testNoAggregateId(): void
    {
        $valueResolver = new AggregateRootIdValueResolver();

        $request = new Request();
        $request->attributes->set('id', '1');

        $argument = new ArgumentMetadata(
            'id',
            'string',
            false,
            false,
            null
        );

        $result = $valueResolver->resolve($request, $argument);

        self::assertEquals([], $result);
    }

    public function testInvalidValue(): void
    {
        $valueResolver = new AggregateRootIdValueResolver();

        $request = new Request();
        $request->attributes->set('id', 5);

        $argument = new ArgumentMetadata(
            'id',
            CustomId::class,
            false,
            false,
            null
        );

        $result = $valueResolver->resolve($request, $argument);

        self::assertEquals([], $result);
    }
}