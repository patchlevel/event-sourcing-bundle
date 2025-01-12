<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\CommandBus;

use Patchlevel\EventSourcing\CommandBus\Handler\ParameterResolver;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

use function strtolower;

final class SymfonyParameterResolver implements ParameterResolver
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /** @return iterable<int, mixed> */
    public function resolve(ReflectionMethod $method, object $command): iterable
    {
        $prefix = strtolower($method->getName()) . '.';

        foreach ($method->getParameters() as $index => $parameter) {
            if ($index === 0) {
                yield $command; // first parameter is always the command

                continue;
            }

            yield $this->container->get($prefix . $parameter->getName());
        }
    }
}
