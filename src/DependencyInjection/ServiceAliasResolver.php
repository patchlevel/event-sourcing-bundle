<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @interal
 */
final readonly class ServiceAliasResolver
{
    public static function resolve(ContainerBuilder $container, string $id): string
    {
        if ($container->hasAlias($id)) {
            return self::resolve(
                $container,
                (string)$container->getAlias($id),
            );
        }

        return $id;
    }
}