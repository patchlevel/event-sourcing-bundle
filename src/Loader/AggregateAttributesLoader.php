<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Loader;

use Patchlevel\EventSourcingBundle\Attributes\Aggregate;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

use function count;
use function get_declared_classes;
use function is_string;

class AggregateAttributesLoader
{
    /** @var list<string> */
    private array $paths;

    /**
     * @param list<string> $paths
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    /**
     * @return array<string, array{class: string, snapshot_store: ?string}>
     */
    public function load(): array
    {
        if (count($this->paths) === 0) {
            return [];
        }

        $files = (new Finder())
            ->in($this->paths)
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
            $attributes = $reflection->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getName() !== Aggregate::class) {
                    continue;
                }

                $aggregate = $attribute->newInstance();
                if (!$aggregate instanceof Aggregate) {
                    continue;
                }

                $attributedAggregateClasses[$aggregate->getName()] = [
                    'class' => $reflection->getName(),
                    'snapshot_store' => is_string($aggregate->getSnapshotStore()) ? $aggregate->getSnapshotStore() : null,
                ];
            }
        }

        return $attributedAggregateClasses;
    }
}
