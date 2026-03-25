<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Tests\Unit;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function str_starts_with;

final class AllPublicCompilerPass implements CompilerPassInterface
{
    private const SERVICE_PREFIX = 'event_sourcing.';
    private const NAMESPACE_PREFIX = 'Patchlevel\\';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$this->isOwnService($id)) {
                continue;
            }

            $definition->setPublic(true);
        }

        foreach ($container->getAliases() as $id => $alias) {
            if ($this->isOwnService($id) || $this->isOwnService((string)$alias)) {
                $alias->setPublic(true);
            }
        }
    }

    private function isOwnService(string $id): bool
    {
        if (str_starts_with($id, self::SERVICE_PREFIX)) {
            return true;
        }

        return str_starts_with($id, self::NAMESPACE_PREFIX);
    }
}
