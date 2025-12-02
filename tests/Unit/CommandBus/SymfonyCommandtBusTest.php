<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcingBundle\CommandBus\SymfonyCommandBus;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\CreateProfile;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/** @covers \Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus */
final class SymfonyCommandtBusTest extends TestCase
{
    public function testDispatch(): void
    {
        $command = new CreateProfile(
            CustomId::fromString('1'),
        );
        $envelope = new Envelope($command);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($command)
            ->willReturn($envelope);

        $commandBus = new SymfonyCommandBus($messageBus);
        $commandBus->dispatch($command);
    }

    public function testException(): void
    {
        $command = new CreateProfile(
            CustomId::fromString('1'),
        );
        $internalException = new class extends RuntimeException {
        };
        $envelope = new Envelope($command);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException(new HandlerFailedException($envelope, [$internalException]));

        $commandBus = new SymfonyCommandBus($messageBus);

        $this->expectException($internalException::class);

        $commandBus->dispatch($command);
    }


    public function testRecursiveException(): void
    {
        $command = new CreateProfile(
            CustomId::fromString('1'),
        );
        $internalException = new class extends RuntimeException {
        };
        $envelope = new Envelope($command);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException(new HandlerFailedException(
                $envelope,
                [new HandlerFailedException($envelope, [$internalException])]
            ));

        $commandBus = new SymfonyCommandBus($messageBus);
        $this->expectException($internalException::class);

        $commandBus->dispatch($command);
    }
}