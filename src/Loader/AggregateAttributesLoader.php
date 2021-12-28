<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Loader;

use Exception;
use Patchlevel\EventSourcingBundle\Attributes\Aggregate;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;

use function count;
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

        $astLocator = (new BetterReflection())->astLocator();
        $directoriesSourceLocator = new DirectoriesSourceLocator($this->paths, $astLocator);
        $reflector = new DefaultReflector($directoriesSourceLocator);
        $classes = $reflector->reflectAllClasses();

        $attributedAggregateClasses = [];
        foreach ($classes as $class) {
            $attributes = $class->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getName() !== Aggregate::class) {
                    continue;
                }

                $arguments = $attribute->getArguments();

                if (!is_string($arguments['name'])) {
                    throw new Exception('aggregate name must be string');
                }

                $attributedAggregateClasses[$arguments['name']] = [
                    'class' => $class->getName(),
                    'snapshot_store' => isset($arguments['snapshot_store']) && is_string($arguments['snapshot_store']) ? $arguments['snapshot_store'] :  null,
                ];
            }
        }

        return $attributedAggregateClasses;
    }
}
