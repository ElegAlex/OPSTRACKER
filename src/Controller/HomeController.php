<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route(path: '/', name: 'app_home')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Technicien → Interface terrain
        if ($this->isGranted('ROLE_TECHNICIEN') && !$this->isGranted('ROLE_GESTIONNAIRE')) {
            return $this->redirectToRoute('app_terrain_index');
        }

        // Gestionnaire ou Admin → Portfolio campagnes
        return $this->redirectToRoute('app_campagne_index');
    }
}
