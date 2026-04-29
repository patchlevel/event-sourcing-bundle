<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class DoctrineCleanupCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('doctrine')) {
            $container->register(DbalCleanupTaskHandler::class, DbalCleanupTaskHandler::class)
                ->setArguments([
                    new Reference('doctrine'),
                ])
                ->addTag('event_sourcing.cleanup_task_handler');
        } elseif ($container->has('event_sourcing.dbal_public_connection')) {
            $container->register(DbalCleanupTaskHandler::class, DbalCleanupTaskHandler::class)
                ->setArguments([
                    new Reference('event_sourcing.dbal_public_connection'),
                ])
                ->addTag('event_sourcing.cleanup_task_handler');
        }
    }
}
