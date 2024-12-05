<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

use function array_keys;
use function sprintf;

final class SubscriberGuardCompilePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(DoctrineSubscriptionStore::class)) {
            return;
        }

        $definition = $container->getDefinition(DoctrineSubscriptionStore::class);

        $argument = $definition->getArgument(0);

        if (!$argument instanceof Reference) {
            throw new InvalidArgumentException(
                sprintf(
                    'The first argument of the "%s" service must be a reference.',
                    DoctrineSubscriptionStore::class,
                ),
            );
        }

        $subscriptionConnection = $this->resolveService($container, (string)$argument);

        $subscribers = $container->findTaggedServiceIds('event_sourcing.subscriber');

        foreach (array_keys($subscribers) as $id) {
            $definition = $container->getDefinition($id);

            $arguments = $definition->getArguments();

            foreach ($arguments as $index => $argument) {
                if (!$argument instanceof Reference) {
                    continue;
                }

                if ($subscriptionConnection !== $this->resolveService($container, (string)$argument)) {
                    continue;
                }

                $class = $definition->getClass();
                $context = '';

                if ($class !== null) {
                    $context = sprintf(' Argument %s on class %s.', $index + 1, $class);
                }

                throw new InvalidArgumentException(
                    sprintf(
                        'You cannot use the same connection for event sourcing and your subscription.%s',
                        $context,
                    ),
                );
            }
        }
    }

    private function resolveService(ContainerBuilder $container, string $id): string
    {
        if ($container->hasAlias($id)) {
            return $this->resolveService(
                $container,
                (string)$container->getAlias($id),
            );
        }

        return $id;
    }
}
