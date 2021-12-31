<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Loader;

use Patchlevel\EventSourcingBundle\Attributes\Aggregate;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;

use function count;
use function get_declared_classes;

class AggregateAttributesLoader
{
    /**
     * @param list<string> $paths
     *
     * @return array<string, array{class: string, snapshot_store: ?string}>
     *
     * @throws ReflectionException
     */
    public function load(array $paths): array
    {
        if (count($paths) === 0) {
            return [];
        }

        $files = (new Finder())
            ->in($paths)
            ->files()
            ->name('*.php');

        if (!$files->hasResults()) {
            return [];
        }

        foreach ($files as $file) {
            $path = $file->getRealPath();
            if (!$path) {
                continue;
            }

            /** @psalm-suppress all */
            require_once $path;
        }

        $attributedAggregateClasses = [];

        $classes = get_declared_classes();
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Aggregate::class);
            foreach ($attributes as $attribute) {
                $aggregate = $attribute->newInstance();

                $attributedAggregateClasses[$aggregate->getName()] = [
                    'class' => $reflection->getName(),
                    'snapshot_store' => $aggregate->getSnapshotStore(),
                ];
            }
        }

        return $attributedAggregateClasses;
    }
}
