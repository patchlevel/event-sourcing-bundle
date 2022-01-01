<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Loader;

use InvalidArgumentException;
use Patchlevel\EventSourcingBundle\Attributes\Aggregate;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;

use function array_key_exists;
use function count;
use function get_declared_classes;
use function sprintf;

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

                if (array_key_exists($aggregate->getName(), $attributedAggregateClasses)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'found duplicate aggregate name "%s", it was found in class "%s" and "%s"',
                            $aggregate->getName(),
                            $class,
                            $attributedAggregateClasses[$aggregate->getName()]['class']
                        )
                    );
                }

                $attributedAggregateClasses[$aggregate->getName()] = [
                    'class' => $reflection->getName(),
                    'snapshot_store' => $aggregate->getSnapshotStore(),
                ];
            }
        }

        return $attributedAggregateClasses;
    }
}
