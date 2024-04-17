<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\DataCollector;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcingBundle\DataCollector\EventSourcingCollector;
use Patchlevel\EventSourcingBundle\DataCollector\MessageListener;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Profile;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileCreated;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

final class EventSourcingCollectorTest extends TestCase
{
    use ProphecyTrait;

    public function testCollectData(): void
    {
        $messageListener = new MessageListener();
        $eventRegistry = new EventRegistry([
            'profile.created' => ProfileCreated::class,
        ]);

        $aggregateRootRegistry = new AggregateRootRegistry([
            'profile' => Profile::class,
        ]);

        $event = new ProfileCreated(new CustomId('1'));

        $message = Message::createWithHeaders($event, [
            new AggregateHeader(
                'profile',
                '1',
                1,
                new DateTimeImmutable('2022-07-07T18:55:50+02:00'),
            )
        ]);

        $eventSerializer = $this->prophesize(EventSerializer::class);

        $eventSerializer->serialize($event, [
            Encoder::OPTION_PRETTY_PRINT => true
        ])->willReturn(new SerializedEvent('profile.created', '{}'));

        $collector = new EventSourcingCollector(
            $messageListener,
            $aggregateRootRegistry,
            $eventRegistry,
            $eventSerializer->reveal()
        );

        $messageListener($message);

        $collector->collect(
            new Request(),
            new Response()
        );

        self::assertSame(['profile' => Profile::class], $collector->getAggregates());
        self::assertSame(['profile.created' => ProfileCreated::class], $collector->getEvents());

        $messages = $collector->getMessages();

        self::assertCount(1, $messages);

        $message = $messages[0];

        self::assertEquals(ProfileCreated::class, $message['event_class']);
        self::assertEquals('profile.created', $message['event_name']);
        self::assertEquals('{}', $message['payload']);
        self::assertEquals(Profile::class, $message['aggregate_class']);
        self::assertEquals('1', $message['aggregate_id']);
        self::assertEquals('2022-07-07T18:55:50+02:00', $message['recorded_on']);
        self::assertInstanceOf(Data::class, $message['headers']);
    }
}
