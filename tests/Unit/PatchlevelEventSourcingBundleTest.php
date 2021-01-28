<?php

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcingBundle\DependencyInjection\PatchlevelEventSourcingExtension;
use Patchlevel\EventSourcingBundle\PatchlevelEventSourcingBundle;
use Patchlevel\EventSourcingBundle\Tests\Fixtures\Profile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\MessageBusInterface;

class PatchlevelEventSourcingBundleTest extends TestCase
{
    use ProphecyTrait;

    public function testDefaultBuild()
    {
        $container = new ContainerBuilder();
        $bundle = new PatchlevelEventSourcingBundle();

        $bundle->build($container);

        $container->set('doctrine.dbal.default_connection', $this->prophesize(Connection::class)->reveal());
        $container->set('event.bus', $this->prophesize(MessageBusInterface::class)->reveal());

        $extension = new PatchlevelEventSourcingExtension();
        $extension->load([
            'patchlevel_event_sourcing' => [
                'message_bus' => 'event.bus',
                'aggregates' => [
                    Profile::class => 'profile'
                ]
            ]
        ], $container);

        $container->compile();

        self::assertInstanceOf(Store::class, $container->get(Store::class));
        self::assertInstanceOf(Repository::class, $container->get('event_sourcing.profile_repository'));
    }
}
