<?php

namespace Patchlevel\EventSourcingBundle\Controller;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

#[Route('/store')]
final class StoreController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Store $store,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly EventRegistry $eventRegistry,
    )
    {
    }

    #[Route('/')]
    public function showAction(): Response
    {
        $messages = $this->store->load(
            null,
            true,
        );

        return new Response(
            $this->twig->render('@PatchlevelEventSourcing/Store/show.html.twig', [
                'messages' => $messages,
                'aggregateNames' => $this->aggregateRootRegistry->aggregateNames(),
                'eventNames' => $this->eventRegistry->eventNames(),
            ])
        );
    }
}
