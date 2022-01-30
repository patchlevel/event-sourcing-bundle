<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Loader;

use Patchlevel\EventSourcingBundle\Attribute\Aggregate;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

use function array_key_exists;
use function count;
use function get_declared_classes;
use function in_array;

final class AggregateAttributesLoader
{
    /**
     * @param list<string> $paths
     *
     * @return array<string, array{class: string, snapshot_store: ?string}>
     */
    public function load(array $paths): array
    {
        $files = $this->findPhpFiles($paths);

        if (count($files) === 0) {
            return [];
        }

        $classes = $this->findAggregateClassesInFiles($files);

        return $this->buildAggregateDefinitions($classes);
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function findPhpFiles(array $paths): array
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

        $result = [];

        foreach ($files as $file) {
            $path = $file->getRealPath();

            if (!$path) {
                continue;
            }

            $result[] = $path;
        }

        return $result;
    }

    /**
     * @param list<string> $files
     *
     * @return list<class-string>
     */
    private function findAggregateClassesInFiles(array $files): array
    {
        foreach ($files as $file) {

            /** @psalm-suppress all */
            require_once $file;
        }

        $classes = get_declared_classes();
        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $fileName = $reflection->getFileName();

            if ($fileName === false) {
                continue;
            }

            if (!in_array($fileName, $files, true)) {
                continue;
            }

            if (count($reflection->getAttributes(Aggregate::class)) === 0) {
                continue;
            }

            $result[] = $class;
        }

        return $result;
    }

    /**
     * @param list<class-string> $classes
     *
     * @return array<string, array{class: string, snapshot_store: ?string}>
     */
    private function buildAggregateDefinitions(array $classes): array
    {
        $definition = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Aggregate::class);

            foreach ($attributes as $attribute) {
                $aggregate = $attribute->newInstance();

                if (array_key_exists($aggregate->getName(), $definition)) {
                    throw new DuplicateAggregateDefinition(
                        $aggregate->getName(),
                        $definition[$aggregate->getName()]['class'],
                        $reflection->getName()
                    );
                }

                $definition[$aggregate->getName()] = [
                    'class' => $reflection->getName(),
                    'snapshot_store' => $aggregate->getSnapshotStore(),
                ];
            }
        }

        return $definition;
    }
}
