<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\RequestListener;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcingBundle\RequestListener\SubscriptionRebuildAfterFileChangeListener;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\FromBeginningSubscriber;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\FromNowSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function filemtime;

/** @covers \Patchlevel\EventSourcingBundle\RequestListener\SubscriptionRebuildAfterFileChangeListener */
final class SubscriptionRebuildAfterFileChangeListenerTest extends TestCase
{
    public function testSkipSubRequest(): void
    {
        $subscriptionEngine = $this->createMock(SubscriptionEngine::class);
        $subscriptionEngine->expects($this->never())->method('execute');

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->never())->method('getItem');

        $listener = new SubscriptionRebuildAfterFileChangeListener(
            $subscriptionEngine,
            [new FromBeginningSubscriber()],
            $cache,
        );

        $listener->onKernelRequest($this->createRequestEvent('/app', HttpKernelInterface::SUB_REQUEST));
    }

    public function testSkipExcludedUrl(): void
    {
        $subscriptionEngine = $this->createMock(SubscriptionEngine::class);
        $subscriptionEngine->expects($this->never())->method('execute');

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->never())->method('getItem');

        $listener = new SubscriptionRebuildAfterFileChangeListener(
            $subscriptionEngine,
            [new FromBeginningSubscriber()],
            $cache,
            excludeUrl: '^/_wdt',
        );

        $listener->onKernelRequest($this->createRequestEvent('/_wdt/abc'));
    }

    public function testRebuildChangedFromBeginningSubscriptionsOnly(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('get')->willReturn(1);
        $item->expects($this->once())->method('set')->with($this->isType('int'))->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with('from-beginning')->willReturn($item);
        $cache->expects($this->once())->method('save')->with($item)->willReturn(true);

        $subscriptionEngine = $this->createMock(SubscriptionEngine::class);
        $subscriptionEngine
            ->expects($matcher = $this->exactly(3))
            ->method('execute')
            ->willReturnCallback(static function (Command $command) use ($matcher): Result {
                self::assertInstanceOf(
                    match ($matcher->numberOfInvocations()) {
                        1 => Remove::class,
                        2 => Setup::class,
                        default => Boot::class,
                    },
                    $command,
                );
                self::assertSame(['from-beginning'], $command->ids);
                self::assertNull($command->groups);

                return $command instanceof Boot ? new ProcessedResult(0) : new Result();
            });

        $listener = new SubscriptionRebuildAfterFileChangeListener(
            $subscriptionEngine,
            [new FromBeginningSubscriber(), new FromNowSubscriber()],
            $cache,
        );

        $listener->onKernelRequest($this->createRequestEvent('/app'));
    }

    public function testNoRebuildWhenFileDidNotChange(): void
    {
        $subscriberFile = (new ReflectionClass(FromBeginningSubscriber::class))->getFileName();
        self::assertIsString($subscriberFile);

        $currentModified = filemtime($subscriberFile);
        self::assertIsInt($currentModified);

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('get')->willReturn($currentModified);
        $item->expects($this->never())->method('set');

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with('from-beginning')->willReturn($item);
        $cache->expects($this->never())->method('save');

        $subscriptionEngine = $this->createMock(SubscriptionEngine::class);
        $subscriptionEngine
            ->expects($matcher = $this->exactly(3))
            ->method('execute')
            ->willReturnCallback(static function (Command $command) use ($matcher): Result {
                self::assertInstanceOf(
                    match ($matcher->numberOfInvocations()) {
                        1 => Remove::class,
                        2 => Setup::class,
                        default => Boot::class,
                    },
                    $command,
                );
                self::assertSame([], $command->ids);

                return $command instanceof Boot ? new ProcessedResult(0) : new Result();
            });

        $listener = new SubscriptionRebuildAfterFileChangeListener(
            $subscriptionEngine,
            [new FromBeginningSubscriber()],
            $cache,
            excludeUrl: '^/_profiler',
        );

        $listener->onKernelRequest($this->createRequestEvent('/app'));
    }

    private function createRequestEvent(
        string $uri,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($uri),
            $requestType,
        );
    }
}
