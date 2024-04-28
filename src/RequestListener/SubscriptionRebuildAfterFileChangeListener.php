<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\RequestListener;

use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use Symfony\Component\HttpKernel\Event\RequestEvent;

use function filemtime;

final class SubscriptionRebuildAfterFileChangeListener
{
    /** @param iterable<object> $subscribers */
    public function __construct(
        private readonly SubscriptionEngine $subscriptionEngine,
        private readonly iterable $subscribers,
        private readonly CacheItemPoolInterface $cache,
        private readonly SubscriberMetadataFactory $metadataFactory = new AttributeSubscriberMetadataFactory(),
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $toRemove = [];
        $itemsToSave = [];

        foreach ($this->subscribers as $subscriber) {
            $metadata = $this->metadataFactory->metadata($subscriber::class);

            if ($metadata->runMode !== RunMode::FromBeginning) {
                continue;
            }

            $item = $this->cache->getItem($metadata->id);

            if (!$item->isHit()) {
                $item->set($this->getLastModifiedTime($subscriber));
                $this->cache->save($item);

                continue;
            }

            /** @var int|null $lastModified */
            $lastModified = $item->get();
            $currentModified = $this->getLastModifiedTime($subscriber);

            if ($lastModified === $currentModified) {
                continue;
            }

            $item->set($currentModified);

            $toRemove[] = $metadata->id;
            $itemsToSave[] = $item;
        }

        $criteria = new SubscriptionEngineCriteria($toRemove);

        $this->subscriptionEngine->remove($criteria);
        $this->subscriptionEngine->setup($criteria);
        $this->subscriptionEngine->boot($criteria);

        foreach ($itemsToSave as $item) {
            $this->cache->save($item);
        }
    }

    private function getLastModifiedTime(object $subscriber): int|null
    {
        $filename = (new ReflectionClass($subscriber))->getFileName();

        if ($filename === false) {
            return null;
        }

        $lastModified = filemtime($filename);

        if ($lastModified === false) {
            return null;
        }

        return $lastModified;
    }
}
