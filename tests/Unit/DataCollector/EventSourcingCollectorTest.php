<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\DataCollector;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcingBundle\DataCollector\EventSourcingCollector;
use Patchlevel\EventSourcingBundle\DataCollector\MessageListener;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Profile;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileCreated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EventSourcingCollectorTest extends TestCase
{
    public function testCollectData(): void
    {
        $messageListener = new MessageListener();
        $eventRegistry = new EventRegistry([
            'profile.created' => ProfileCreated::class,
        ]);

        $aggregateRootRegistry = new AggregateRootRegistry([
            'profile' => Profile::class,
        ]);

        $collector = new EventSourcingCollector(
            $messageListener,
            $aggregateRootRegistry,
            $eventRegistry
        );

        $message = new Message(
            new ProfileCreated('1')
        );

        $messageListener($message);

        $collector->collect(
            new Request(),
            new Response()
        );

        self::assertSame(['profile' => Profile::class], $collector->getAggregates());
        self::assertSame(['profile.created' => ProfileCreated::class], $collector->getEvents());
        self::assertCount(1, $collector->getMessages());
    }
}
