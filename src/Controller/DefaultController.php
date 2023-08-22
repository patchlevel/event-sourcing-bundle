<?php

namespace Patchlevel\EventSourcingBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

final class DefaultController
{
    public function __construct(
        private readonly Environment     $twig,
        private readonly RouterInterface $router,
    )
    {
    }

    #[Route('/')]
    public function indexAction(): Response
    {
        return new RedirectResponse($this->router->generate('patchlevel_event_sourcing_store_show'));
    }

    #[Route('/style.css')]
    public function styleAction(): Response
    {
        return new Response(
            $this->twig->render('@PatchlevelEventSourcing/style.css.twig'),
            200,
            [
                'Content-Type' => 'text/css',
            ]
        );
    }
}
