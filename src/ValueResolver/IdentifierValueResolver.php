<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\ValueResolver;

use Patchlevel\EventSourcing\Identifier\Identifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

use function is_a;
use function is_string;

final class IdentifierValueResolver implements ValueResolverInterface
{
    /** @return iterable<Identifier> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $argumentType = $argument->getType();

        if ($argumentType === null || !is_a($argumentType, Identifier::class, true)) {
            return [];
        }

        $value = $request->attributes->get($argument->getName());

        if (!is_string($value)) {
            return [];
        }

        return [$argumentType::fromString($value)];
    }
}
