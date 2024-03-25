<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle;

use Patchlevel\EventSourcingBundle\DependencyInjection\TraceCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PatchlevelEventSourcingBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TraceCompilerPass());
    }
}
