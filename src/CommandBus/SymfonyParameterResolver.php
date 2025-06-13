<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\CommandBus;

use Patchlevel\EventSourcing\CommandBus\Handler\ParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\ServiceNotResolvable;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

use function strtolower;

final class SymfonyParameterResolver implements ParameterResolver
{
    private readonly TypeResolver $typeResolver;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        $this->typeResolver = TypeResolver::create();
    }

    /** @return iterable<int, mixed> */
    public function resolve(ReflectionMethod $method, object $command): iterable
    {
        $prefix = strtolower($method->getName()) . '.';

        foreach ($method->getParameters() as $parameter) {
            $reflectionType = $parameter->getType();

            if ($reflectionType) {
                $type = $this->typeResolver->resolve($reflectionType);

                if ($type->isIdentifiedBy($command::class)) {
                    yield $command;

                    continue;
                }
            }

            try {
                yield $this->container->get($prefix . $parameter->getName());
            } catch (ContainerExceptionInterface $exception) {
                throw ServiceNotResolvable::missingService(
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $parameter->getName(),
                    $exception,
                );
            }
        }
    }
}
