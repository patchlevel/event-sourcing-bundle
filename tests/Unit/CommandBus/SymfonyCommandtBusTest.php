<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcingBundle\CommandBus\SymfonyCommandBus;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\CreateProfile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/** @covers \Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus */
final class SymfonyCommandtBusTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatch(): void
    {
        $command = new CreateProfile(
            CustomId::fromString('1'),
        );

        $symfony = $this->prophesize(MessageBusInterface::class);

        $envelope = new Envelope($command);

        $symfony->dispatch($command)->willReturn($envelope)->shouldBeCalled();

        $commandBus = new SymfonyCommandBus($symfony->reveal());
        $commandBus->dispatch($command);
    }

    public function testException(): void
    {
        $command = new CreateProfile(
            CustomId::fromString('1'),
        );

        $symfony = $this->prophesize(MessageBusInterface::class);

        $internalException = new class extends RuntimeException {
        };

        $envelope = new Envelope($command);

        $symfony
            ->dispatch($command)
            ->willThrow(new HandlerFailedException($envelope, [$internalException]))
            ->shouldBeCalled();

        $commandBus = new SymfonyCommandBus($symfony->reveal());

        $this->expectException($internalException::class);

        $commandBus->dispatch($command);
    }


    public function testRecursiveException(): void
    {
        $command = new CreateProfile(
            CustomId::fromString('1'),
        );

        $symfony = $this->prophesize(MessageBusInterface::class);

        $internalException = new class extends RuntimeException {
        };

        $envelope = new Envelope($command);

        $symfony
            ->dispatch($command)
            ->willThrow(new HandlerFailedException($envelope, [new HandlerFailedException($envelope, [$internalException])]))
            ->shouldBeCalled();

        $commandBus = new SymfonyCommandBus($symfony->reveal());

        $this->expectException($internalException::class);

        $commandBus->dispatch($command);
    }
}