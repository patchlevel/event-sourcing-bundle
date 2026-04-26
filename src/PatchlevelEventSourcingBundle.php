<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle;

use Patchlevel\EventSourcingBundle\DependencyInjection\CommandHandlerCompilerPass;
use Patchlevel\EventSourcingBundle\DependencyInjection\DoctrineCleanupCompilerPass;
use Patchlevel\EventSourcingBundle\DependencyInjection\HandlerServiceLocatorCompilerPass;
use Patchlevel\EventSourcingBundle\DependencyInjection\HydratorCompilerPass;
use Patchlevel\EventSourcingBundle\DependencyInjection\QueryHandlerCompilerPass;
use Patchlevel\EventSourcingBundle\DependencyInjection\RepositoryCompilerPass;
use Patchlevel\EventSourcingBundle\DependencyInjection\SubscriberGuardCompilePass;
use Patchlevel\EventSourcingBundle\DependencyInjection\TranslatorCompilerPass;
use Patchlevel\EventSourcingBundle\DependencyInjection\WorkerResetCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PatchlevelEventSourcingBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RepositoryCompilerPass());
        $container->addCompilerPass(new SubscriberGuardCompilePass());
        $container->addCompilerPass(new CommandHandlerCompilerPass(), priority: 100);
        $container->addCompilerPass(new QueryHandlerCompilerPass(), priority: 100);
        $container->addCompilerPass(new HandlerServiceLocatorCompilerPass(), priority: -100);
        $container->addCompilerPass(new TranslatorCompilerPass());
        $container->addCompilerPass(new DoctrineCleanupCompilerPass());
        $container->addCompilerPass(new HydratorCompilerPass());
        $container->addCompilerPass(new WorkerResetCompilerPass());
    }
}
