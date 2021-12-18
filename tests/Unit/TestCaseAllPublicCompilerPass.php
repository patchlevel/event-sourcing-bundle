<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TestCaseAllPublicCompilerPass implements CompilerPassInterface
{
    private const SERVICE_PREFIX = 'event_sourcing.';
    private const NAMESPACE_PREFIX = 'Patchlevel\\EventSourcing\\';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->isOwnService($id)) {
                $definition->setPublic(true);
            }
        }

        foreach ($container->getAliases() as $id => $alias) {
            if ($this->isOwnService($id)) {
                $alias->setPublic(true);
            }
        }
    }

    private function isOwnService(string $id): bool
    {
        if (strpos($id, self::SERVICE_PREFIX) === 0) {
            return true;
        }

        return strpos($id, self::NAMESPACE_PREFIX) === 0;
    }
}