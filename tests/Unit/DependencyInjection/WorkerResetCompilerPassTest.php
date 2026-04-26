<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit\DependencyInjection;

use Patchlevel\EventSourcingBundle\DependencyInjection\WorkerResetCompilerPass;
use Patchlevel\EventSourcingBundle\Subscription\ResetServicesListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

final class WorkerResetCompilerPassTest extends TestCase
{
    public function testCreatesFilteredResetterWhenDebugDispatcherExists(): void
    {
        $container = new ContainerBuilder();

        $container->register(ResetServicesListener::class)
            ->setArguments([new Reference('services_resetter')]);

        $container->register('debug.event_dispatcher')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register('some.other.service')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $pass = new WorkerResetCompilerPass();
        $pass->process($container);

        self::assertTrue($container->hasDefinition('patchlevel.worker.services_resetter'));

        $definition = $container->getDefinition('patchlevel.worker.services_resetter');
        self::assertSame(ServicesResetter::class, $definition->getClass());

        /** @var array<string, list<string>> $methods */
        $methods = $definition->getArgument(1);
        self::assertArrayNotHasKey('debug.event_dispatcher', $methods);
        self::assertArrayHasKey('some.other.service', $methods);
        self::assertSame(['reset'], $methods['some.other.service']);

        $listenerDef = $container->getDefinition(ResetServicesListener::class);
        self::assertEquals(
            new Reference('patchlevel.worker.services_resetter'),
            $listenerDef->getArgument(0),
        );
    }

    public function testSkipsWhenNoDebugDispatcher(): void
    {
        $container = new ContainerBuilder();

        $container->register(ResetServicesListener::class)
            ->setArguments([new Reference('services_resetter')]);

        $container->register('some.other.service')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $pass = new WorkerResetCompilerPass();
        $pass->process($container);

        self::assertFalse($container->hasDefinition('patchlevel.worker.services_resetter'));

        $listenerDef = $container->getDefinition(ResetServicesListener::class);
        self::assertEquals(
            new Reference('services_resetter'),
            $listenerDef->getArgument(0),
        );
    }

    public function testSkipsWhenNoResetServicesListener(): void
    {
        $container = new ContainerBuilder();

        $container->register('debug.event_dispatcher')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register('some.other.service')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $pass = new WorkerResetCompilerPass();
        $pass->process($container);

        // The pass must not create the worker resetter when the listener is absent.
        self::assertFalse($container->hasDefinition('patchlevel.worker.services_resetter'));
    }

    public function testSkipsWhenNoResettableServicesRemain(): void
    {
        $container = new ContainerBuilder();

        $container->register(ResetServicesListener::class)
            ->setArguments([new Reference('services_resetter')]);

        $container->register('debug.event_dispatcher')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $pass = new WorkerResetCompilerPass();
        $pass->process($container);

        self::assertFalse($container->hasDefinition('patchlevel.worker.services_resetter'));
    }

    public function testHandlesOnInvalidIgnoreAttribute(): void
    {
        $container = new ContainerBuilder();

        $container->register(ResetServicesListener::class)
            ->setArguments([new Reference('services_resetter')]);

        $container->register('debug.event_dispatcher')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register('some.service')
            ->addTag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore']);

        $pass = new WorkerResetCompilerPass();
        $pass->process($container);

        self::assertTrue($container->hasDefinition('patchlevel.worker.services_resetter'));

        /** @var array<string, list<string>> $methods */
        $methods = $container->getDefinition('patchlevel.worker.services_resetter')->getArgument(1);
        self::assertSame(['?reset'], $methods['some.service']);
    }

    public function testSkipsTagsWithoutMethodAttribute(): void
    {
        $container = new ContainerBuilder();

        $container->register(ResetServicesListener::class)
            ->setArguments([new Reference('services_resetter')]);

        $container->register('debug.event_dispatcher')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register('some.service')
            ->addTag('kernel.reset', [])
            ->addTag('kernel.reset', ['method' => 'reset']);

        $pass = new WorkerResetCompilerPass();
        $pass->process($container);

        /** @var array<string, list<string>> $methods */
        $methods = $container->getDefinition('patchlevel.worker.services_resetter')->getArgument(1);
        self::assertSame(['reset'], $methods['some.service']);
    }

    public function testPreservesMultipleResetMethodsOnSameService(): void
    {
        $container = new ContainerBuilder();

        $container->register(ResetServicesListener::class)
            ->setArguments([new Reference('services_resetter')]);

        $container->register('debug.event_dispatcher')
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register('some.service')
            ->addTag('kernel.reset', ['method' => 'resetA'])
            ->addTag('kernel.reset', ['method' => 'resetB']);

        $pass = new WorkerResetCompilerPass();
        $pass->process($container);

        /** @var array<string, list<string>> $methods */
        $methods = $container->getDefinition('patchlevel.worker.services_resetter')->getArgument(1);
        self::assertSame(['resetA', 'resetB'], $methods['some.service']);
    }
}
