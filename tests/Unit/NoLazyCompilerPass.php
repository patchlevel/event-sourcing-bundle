<?php

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NoLazyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            $definition->setLazy(false);
        }
    }
}