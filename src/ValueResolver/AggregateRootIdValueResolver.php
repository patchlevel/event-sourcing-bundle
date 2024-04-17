<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\ValueResolver;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

use function is_a;
use function is_string;

final class AggregateRootIdValueResolver implements ValueResolverInterface
{
    /** @return iterable<AggregateRootId> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $argumentType = $argument->getType();

        if ($argumentType === null || !is_a($argumentType, AggregateRootId::class, true)) {
            return [];
        }

        $value = $request->attributes->get($argument->getName());

        if (!is_string($value)) {
            return [];
        }

        return [$argumentType::fromString($value)];
    }
}
