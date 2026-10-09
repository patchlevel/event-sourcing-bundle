<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\RequestListener;

use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use Symfony\Component\HttpKernel\Event\RequestEvent;

use function filemtime;
use function preg_match;

final class SubscriptionRebuildAfterFileChangeListener
{
    /** @param iterable<object> $subscribers */
    public function __construct(
        private readonly SubscriptionEngine $subscriptionEngine,
        private readonly iterable $subscribers,
        private readonly CacheItemPoolInterface $cache,
        private readonly SubscriberMetadataFactory $metadataFactory = new AttributeSubscriberMetadataFactory(),
        private readonly string|null $excludeUrl = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (
            $this->excludeUrl !== null
            && preg_match('#' . $this->excludeUrl . '#', $event->getRequest()->getRequestUri())
        ) {
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

        $this->subscriptionEngine->execute(new Remove($toRemove));
        $this->subscriptionEngine->execute(new Setup($toRemove));
        $this->subscriptionEngine->execute(new Boot($toRemove));

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
