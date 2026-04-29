<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\RequestListener;

use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcingBundle\RequestListener\AutoSetupListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** @covers \Patchlevel\EventSourcingBundle\RequestListener\AutoSetupListener */
final class AutoSetupListenerTest extends TestCase
{
    public function testSkipSubRequest(): void
    {
        $subscriptionEngine = $this->createMock(SubscriptionEngine::class);
        $subscriptionEngine->expects($this->never())->method('subscriptions');
        $subscriptionEngine->expects($this->never())->method('setup');

        $listener = new AutoSetupListener($subscriptionEngine, null, null);
        $listener->onKernelRequest($this->createRequestEvent('/foo', HttpKernelInterface::SUB_REQUEST));
    }

    public function testSkipExcludedUrl(): void
    {
        $subscriptionEngine = $this->createMock(SubscriptionEngine::class);
        $subscriptionEngine->expects($this->never())->method('subscriptions');
        $subscriptionEngine->expects($this->never())->method('setup');

        $listener = new AutoSetupListener($subscriptionEngine, null, null, '^/_profiler');
        $listener->onKernelRequest($this->createRequestEvent('/_profiler/test'));
    }

    public function testSetupOnlyNewSubscriptions(): void
    {
        $subscriptionEngine = $this->createMock(SubscriptionEngine::class);
        $subscriptionEngine
            ->expects($this->once())
            ->method('subscriptions')
            ->with($this->callback(static function (SubscriptionEngineCriteria|null $criteria): bool {
                return $criteria instanceof SubscriptionEngineCriteria
                    && $criteria->ids === ['id-1']
                    && $criteria->groups === ['group-1'];
            }))
            ->willReturn([
                new Subscription('new-1'),
                new Subscription('active-1', status: Status::Active),
                new Subscription('new-2'),
            ]);

        $subscriptionEngine
            ->expects($this->once())
            ->method('setup')
            ->with($this->callback(static function (SubscriptionEngineCriteria|null $criteria): bool {
                return $criteria instanceof SubscriptionEngineCriteria
                    && $criteria->ids === ['new-1', 'new-2']
                    && $criteria->groups === null;
            }), true)
            ->willReturn(new Result());

        $listener = new AutoSetupListener($subscriptionEngine, ['id-1'], ['group-1'], '^/_profiler');
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
