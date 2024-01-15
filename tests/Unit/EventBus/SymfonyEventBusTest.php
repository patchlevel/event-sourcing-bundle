<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\ProfileCreated;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/** @covers \Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus */
final class SymfonyEventBusTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatchEvent(): void
    {
        $message = new Message(
            new ProfileCreated(
                CustomId::fromString('1'),
            ),
        );

        $envelope = new Envelope($message);

        $symfony = $this->prophesize(MessageBusInterface::class);
        $symfony->dispatch(Argument::that(static function ($envelope) use ($message) {
            if (!$envelope instanceof Envelope) {
                return false;
            }

            return $envelope->getMessage() === $message;
        }))->willReturn($envelope)->shouldBeCalled();

        $eventBus = new SymfonyEventBus($symfony->reveal());
        $eventBus->dispatch($message);
    }

    public function testDispatchMultipleMessages(): void
    {
        $message1 = new Message(
            new ProfileCreated(
                CustomId::fromString('1'),
            ),
        );

        $message2 = new Message(
            new ProfileCreated(
                CustomId::fromString('1'),
            ),
        );

        $envelope1 = new Envelope($message1);

        $symfony = $this->prophesize(MessageBusInterface::class);
        $symfony->dispatch(Argument::that(static function ($envelope1) use ($message1) {
            if (!$envelope1 instanceof Envelope) {
                return false;
            }

            return $envelope1->getMessage() === $message1;
        }))->willReturn($envelope1)->shouldBeCalled();

        $envelope2 = new Envelope($message2);

        $symfony->dispatch(Argument::that(static function ($envelope2) use ($message2) {
            if (!$envelope2 instanceof Envelope) {
                return false;
            }

            return $envelope2->getMessage() === $message2;
        }))->willReturn($envelope2)->shouldBeCalled();

        $eventBus = new SymfonyEventBus($symfony->reveal());
        $eventBus->dispatch($message1, $message2);
    }
}