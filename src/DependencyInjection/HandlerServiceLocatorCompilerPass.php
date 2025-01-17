<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Attribute\Inject;
use Patchlevel\EventSourcing\CommandBus\Handler\ServiceNotResolvable;
use Patchlevel\EventSourcing\CommandBus\HandlerFinder;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcingBundle\CommandBus\SymfonyParameterResolver;
use Psr\Container\ContainerInterface;
use ReflectionAttribute;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

use function sprintf;
use function strtolower;

/** @internal */
final class HandlerServiceLocatorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('patchlevel_event_sourcing.aggregate_handlers.bus')) {
            return;
        }

        /** @var AggregateRootRegistry $aggregateRootRegistry */
        $aggregateRootRegistry = $container->get(AggregateRootRegistry::class);

        foreach ($aggregateRootRegistry->aggregateClasses() as $aggregateName => $aggregateClass) {
            $parameterResolverId = sprintf('.event_sourcing.handler_parameter_resolver.%s', $aggregateName);
            $services = [];

            foreach (HandlerFinder::findInClass($aggregateClass) as $aggregateHandler) {
                $services += $this->services(new ReflectionMethod($aggregateClass, $aggregateHandler->method), $container);
            }

            $container->register($parameterResolverId, SymfonyParameterResolver::class)
                ->setArguments([
                    new ServiceLocatorArgument($services),
                ]);
        }
    }

    /** @return array<string, mixed> */
    private function services(ReflectionMethod $method, ContainerInterface $container): array
    {
        $services = [];
        $prefix = strtolower($method->getName()) . '.';

        foreach ($method->getParameters() as $index => $parameter) {
            if ($index === 0) {
                continue; // skip first parameter (command)
            }

            $key = $prefix . $parameter->getName();

            $attributes = $parameter->getAttributes(Inject::class);

            if ($attributes !== []) {
                $services[$key] = new Reference($attributes[0]->newInstance()->service);

                continue;
            }

            $attributes = $parameter->getAttributes(Autowire::class, ReflectionAttribute::IS_INSTANCEOF);

            if ($attributes !== []) {
                $services[$key] = $attributes[0]->newInstance()->value;

                continue;
            }

            $reflectionType = $parameter->getType();

            if ($reflectionType === null) {
                throw ServiceNotResolvable::missingType($method->getDeclaringClass()->getName(), $parameter->getName());
            }

            $type = TypeResolver::create()->resolve($reflectionType);

            if (!$type instanceof ObjectType) {
                throw ServiceNotResolvable::typeNotObject(
                    $method->getDeclaringClass()->getName(),
                    $parameter->getName(),
                );
            }

            $binding = sprintf('%s $%s', $type->getClassName(), $parameter->getName());

            if ($container->has($binding)) {
                $services[$key] = new Reference($binding);

                continue;
            }

            $services[$key] = new Reference($type->getClassName());
        }

        return $services;
    }
}
