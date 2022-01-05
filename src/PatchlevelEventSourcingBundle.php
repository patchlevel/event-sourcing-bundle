<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle;

use Patchlevel\EventSourcingBundle\DependencyInjection\Compiler\ProcessorPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PatchlevelEventSourcingBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ProcessorPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 16);
    }
}
