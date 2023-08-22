<?php

namespace Patchlevel\EventSourcingBundle\Controller;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

#[Route('/projection')]
final class ProjectionController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Projectionist $projectionist,
        private readonly Store $store,
        private readonly RouterInterface $router,
    )
    {
    }

    #[Route('/')]
    public function showAction(): Response
    {
        $projections = $this->projectionist->projections();
        $messageCount = $this->store->count();

        return new Response(
            $this->twig->render('@PatchlevelEventSourcing/Projection/show.html.twig', [
                'projections' => $projections,
                'messageCount' => $messageCount,
            ])
        );
    }

    #[Route('/{id}/rebuild')]
    public function rebuildAction(string $id): Response
    {
        $criteria = new ProjectionCriteria([
            ProjectionId::fromString($id),
        ]);

        $this->projectionist->remove($criteria);
        $this->projectionist->boot($criteria);

        return new RedirectResponse(
            $this->router->generate('patchlevel_eventsourcing_projection_show')
        );
    }
}
