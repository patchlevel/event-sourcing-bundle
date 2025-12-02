<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Identifier\CustomId;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileCreated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/** @covers \Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus */
final class SymfonyEventBusTest extends TestCase
{
    public function testDispatchEvent(): void
    {
        $message = new Message(
            new ProfileCreated(
                CustomId::fromString('1'),
            ),
        );
        $envelope = (new Envelope($message))->with(new DispatchAfterCurrentBusStamp());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($envelope)
            ->willReturn($envelope);

        $eventBus = new SymfonyEventBus($messageBus);
        $eventBus->dispatch($message);
    }

    public function testDispatchMultipleMessages(): void
    {
        $message1 = new Message(new ProfileCreated(CustomId::fromString('1')));
        $message2 = new Message(new ProfileCreated(CustomId::fromString('2')));

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnArgument(0);

        $eventBus = new SymfonyEventBus($messageBus);
        $eventBus->dispatch($message1, $message2);
    }
}