<?php

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcingBundle\DependencyInjection\PatchlevelEventSourcingExtension;
use Patchlevel\EventSourcingBundle\PatchlevelEventSourcingBundle;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Profile;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\SnapshotableProfile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\MessageBusInterface;

class PatchlevelEventSourcingBundleTest extends TestCase
{
    use ProphecyTrait;

    public function testMinimalBuild()
    {
        $container = new ContainerBuilder();
        $bundle = new PatchlevelEventSourcingBundle();

        $bundle->build($container);

        $container->set('doctrine.dbal.default_connection', $this->prophesize(Connection::class)->reveal());

        $extension = new PatchlevelEventSourcingExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertInstanceOf(SingleTableStore::class, $container->get(Store::class));
    }

    public function testFullBuild()
    {
        $container = new ContainerBuilder();
        $bundle = new PatchlevelEventSourcingBundle();

        $bundle->build($container);

        $container->set('doctrine.dbal.eventstore_connection', $this->prophesize(Connection::class)->reveal());
        $container->set('event.bus', $this->prophesize(MessageBusInterface::class)->reveal());
        $container->set('cache.default', $this->prophesize(CacheItemPoolInterface::class)->reveal());

        $extension = new PatchlevelEventSourcingExtension();
        $extension->load([
            'patchlevel_event_sourcing' => [
                'store' => [
                    'type' => 'dbal_multi_table',
                    'dbal_connection' => 'eventstore',
                ],
                'message_bus' => 'event.bus',
                'aggregates' => [
                    'profile' => [
                        'class' => SnapshotableProfile::class,
                        'snapshot' => 'default'
                    ],
                ],
                'migration' => [
                    'namespace' => 'Foo',
                    'path' => 'src'
                ],
                'snapshots' => [
                    'default' => [
                        'type' => 'cache',
                        'cache' => 'default'
                    ]
                ],
                'watch_server' => [
                    'enabled' => true,
                    'host' => 'localhost'
                ]
            ]
        ], $container);

        $container->compile();

        self::assertInstanceOf(MultiTableStore::class, $container->get(Store::class));
        self::assertInstanceOf(Repository::class, $container->get('event_sourcing.profile_repository'));
    }
}
