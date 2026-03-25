<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\RequestListener;

use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcingBundle\RequestListener\SubscriptionRebuildAfterFileChangeListener;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\FromBeginningSubscriber;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\FromNowSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** @covers \Patchlevel\EventSourcingBundle\RequestListener\SubscriptionRebuildAfterFileChangeListener */
final class SubscriptionRebuildAfterFileChangeListenerTest extends TestCase
{
    public function testSkipSubRequest(): void
    {
        $subscriptionEngine = $this->createMock(SubscriptionEngine::class);
        $subscriptionEngine->expects($this->never())->method('remove');
        $subscriptionEngine->expects($this->never())->method('setup');
        $subscriptionEngine->expects($this->never())->method('boot');

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
        $subscriptionEngine->expects($this->never())->method('remove');
        $subscriptionEngine->expects($this->never())->method('setup');
        $subscriptionEngine->expects($this->never())->method('boot');

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
        $criteriaMatcher = $this->callback(static fn (SubscriptionEngineCriteria|null $criteria): bool => $criteria instanceof SubscriptionEngineCriteria && $criteria->ids === ['from-beginning'] && $criteria->groups === null);

        $subscriptionEngine->expects($this->once())->method('remove')->with($criteriaMatcher)->willReturn(new Result());
        $subscriptionEngine->expects($this->once())->method('setup')->with($criteriaMatcher)->willReturn(new Result());
        $subscriptionEngine->expects($this->once())->method('boot')->with($criteriaMatcher)->willReturn(new ProcessedResult(0));

        $listener = new SubscriptionRebuildAfterFileChangeListener(
            $subscriptionEngine,
            [new FromBeginningSubscriber(), new FromNowSubscriber()],
            $cache,
        );

        $listener->onKernelRequest($this->createRequestEvent('/app'));
    }

    public function testNoRebuildWhenFileDidNotChange(): void
    {
        $subscriberFile = (new \ReflectionClass(FromBeginningSubscriber::class))->getFileName();
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
        $emptyCriteriaMatcher = $this->callback(static fn (SubscriptionEngineCriteria|null $criteria): bool => $criteria instanceof SubscriptionEngineCriteria && $criteria->ids === []);

        $subscriptionEngine->expects($this->once())->method('remove')->with($emptyCriteriaMatcher)->willReturn(new Result());
        $subscriptionEngine->expects($this->once())->method('setup')->with($emptyCriteriaMatcher)->willReturn(new Result());
        $subscriptionEngine->expects($this->once())->method('boot')->with($emptyCriteriaMatcher)->willReturn(new ProcessedResult(0));

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
