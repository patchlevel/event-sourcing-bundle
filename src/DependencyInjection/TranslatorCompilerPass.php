<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

use function class_exists;

/** @internal */
final class TranslatorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('event_sourcing.translators')) {
            return;
        }

        $translators = $container->getParameter('event_sourcing.translators');

        foreach ($translators as $priority => $translatorId) {
            if ($container->has($translatorId)) {
                $definition = $container->findDefinition($translatorId);
                $definition->addTag(
                    'event_sourcing.translator',
                    ['priority' => -$priority],
                );

                continue;
            }

            if (class_exists($translatorId)) {
                $container->register($translatorId, $translatorId)
                    ->setAutowired(true)
                    ->addTag(
                        'event_sourcing.translator',
                        ['priority' => -$priority],
                    );

                continue;
            }

            throw new ServiceNotFoundException($translatorId);
        }
    }
}
