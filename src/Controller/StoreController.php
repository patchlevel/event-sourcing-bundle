<?php

namespace Patchlevel\EventSourcingBundle\Controller;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

#[Route('/store')]
final class StoreController
{
    public function __construct(
        private readonly Environment           $twig,
        private readonly Store                 $store,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
    )
    {
    }

    #[Route('/')]
    public function showAction(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 50);

        $criteria = $this->criteria($request);

        $messages = $this->store->load(
            $criteria,
            $limit,
            ($page - 1) * $limit,
            true,
        );

        $count = $this->store->count($criteria);

        return new Response(
            $this->twig->render('@PatchlevelEventSourcing/Store/show.html.twig', [
                'messages' => $messages,
                'count' => $count,
                'aggregates' => $this->aggregateRootRegistry->aggregateNames(),
                'limit' => $limit,
                'page' => $page,
            ])
        );
    }

    private function criteria(Request $request): Criteria
    {
        $aggregateName = $request->query->get('aggregate');
        $aggregateId = $request->query->get('aggregateId');

        return new Criteria(
            aggregateClass: $aggregateName ? $this->aggregateRootRegistry->aggregateClass($aggregateName) : null,
            aggregateId: $aggregateId ?: null,
        );
    }
}
