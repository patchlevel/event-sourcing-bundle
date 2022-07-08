<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\DataCollector;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
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

class EventSourcingCollectorTest extends TestCase
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

        $event = new ProfileCreated('1');

        $message = Message::createWithHeaders($event, [
            Message::HEADER_AGGREGATE_CLASS => Profile::class,
            Message::HEADER_AGGREGATE_ID => '1',
            Message::HEADER_PLAYHEAD => 1,
            Message::HEADER_RECORDED_ON => new DateTimeImmutable('2022-07-07T18:55:50+02:00'),
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
        self::assertInstanceOf(Data::class, $message['custom_headers']);
    }
}
