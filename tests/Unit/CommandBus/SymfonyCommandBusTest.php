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
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/** @covers \Patchlevel\EventSourcingBundle\EventBus\SymfonyEventBus */
final class SymfonyCommandBusTest extends TestCase
{
    public function testDispatch(): void
    {
        $command = new CreateProfile(CustomId::fromString('1'));

        $middleware = new class implements MiddlewareInterface
        {
            public object|null $command = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->command = $envelope->getMessage();

                return $envelope;
            }
        };
        $messageBus = new MessageBus([$middleware]);

        $commandBus = new SymfonyCommandBus($messageBus);
        $commandBus->dispatch($command);

        self::assertNotNull($middleware->command);
        self::assertSame($command, $middleware->command);
    }

    public function testException(): void
    {
        $command = new CreateProfile(CustomId::fromString('1'));

        $middleware = new class implements MiddlewareInterface
        {
            public object|null $command = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->command = $envelope->getMessage();

                throw new RuntimeException('test');
            }
        };
        $messageBus = new MessageBus([$middleware]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^test$/');

        $commandBus = new SymfonyCommandBus($messageBus);
        $commandBus->dispatch($command);

        self::assertNotNull($middleware->command);
        self::assertSame($command, $middleware->command);
    }

    public function testRecursiveException(): void
    {
        $command = new CreateProfile(CustomId::fromString('1'));

        $middleware = new class implements MiddlewareInterface
        {
            public object|null $command = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->command = $envelope->getMessage();

                throw new HandlerFailedException($envelope, [new RuntimeException('test')]);
            }
        };
        $messageBus = new MessageBus([$middleware]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^test$/');

        $commandBus = new SymfonyCommandBus($messageBus);
        $commandBus->dispatch($command);

        self::assertNotNull($middleware->command);
        self::assertSame($command, $middleware->command);
    }

    public function testRecursiveExceptionStringKey(): void
    {
        $command = new CreateProfile(CustomId::fromString('1'));

        $middleware = new class implements MiddlewareInterface
        {
            public object|null $command = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->command = $envelope->getMessage();

                throw new HandlerFailedException($envelope, ['controller-class' => new RuntimeException('test')]);
            }
        };
        $messageBus = new MessageBus([$middleware]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^test$/');

        $commandBus = new SymfonyCommandBus($messageBus);
        $commandBus->dispatch($command);

        self::assertNotNull($middleware->command);
        self::assertSame($command, $middleware->command);
    }
}
