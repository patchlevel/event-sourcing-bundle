<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\ValueResolver;

use Patchlevel\EventSourcing\Identifier\CustomId;
use Patchlevel\EventSourcingBundle\ValueResolver\IdentifierValueResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/** @covers \Patchlevel\EventSourcingBundle\ValueResolver\IdentifierValueResolver */
final class IdentifierValueResolverTest extends TestCase
{
    public function testResolveValue(): void
    {
        $valueResolver = new IdentifierValueResolver();

        $request = new Request();
        $request->attributes->set('id', '1');

        $argument = new ArgumentMetadata(
            'id',
            CustomId::class,
            false,
            false,
            null,
        );

        $result = $valueResolver->resolve($request, $argument);

        self::assertEquals([new CustomId('1')], $result);
    }

    public function testNoAggregateId(): void
    {
        $valueResolver = new IdentifierValueResolver();

        $request = new Request();
        $request->attributes->set('id', '1');

        $argument = new ArgumentMetadata(
            'id',
            'string',
            false,
            false,
            null,
        );

        $result = $valueResolver->resolve($request, $argument);

        self::assertEquals([], $result);
    }

    public function testInvalidValue(): void
    {
        $valueResolver = new IdentifierValueResolver();

        $request = new Request();
        $request->attributes->set('id', 5);

        $argument = new ArgumentMetadata(
            'id',
            CustomId::class,
            false,
            false,
            null,
        );

        $result = $valueResolver->resolve($request, $argument);

        self::assertEquals([], $result);
    }
}
