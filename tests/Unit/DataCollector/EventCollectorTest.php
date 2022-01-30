<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\DataCollector;

use Patchlevel\EventSourcingBundle\DataCollector\EventCollector;
use Patchlevel\EventSourcingBundle\DataCollector\EventListener;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Profile;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileCreated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EventCollectorTest extends TestCase
{
    public function testCollectData(): void
    {
        $eventListener = new EventListener();

        $collector = new EventCollector(
            $eventListener,
            [Profile::class => 'profile']
        );

        $event = ProfileCreated::raise('foo');
        $eventListener($event);

        $collector->collect(
            new Request(),
            new Response()
        );

        self::assertSame([Profile::class => 'profile'], $collector->getAggregates());
        self::assertCount(1, $collector->getEvents());
    }
}
