<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Service\DashboardService;
use App\Service\ShareService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour les liens de partage en lecture seule.
 *
 * User Story : US-605 - Partager une URL lecture seule
 * Regle metier : RG-041 - URLs partagees = consultation uniquement, aucune action
 */
class ShareController extends AbstractController
{
    public function __construct(
        private readonly ShareService $shareService,
        private readonly DashboardService $dashboardService,
    ) {
    }

    /**
     * RG-041 : Dashboard en lecture seule (accessible sans authentification)
     */
    #[Route('/share/{token}', name: 'app_share_dashboard', methods: ['GET'])]
    public function viewShared(string $token): Response
    {
        // Valider le format du token
        if (!$this->shareService->isValidToken($token)) {
            throw $this->createNotFoundException('Lien de partage invalide');
        }

        // Trouver la campagne
        $campagne = $this->shareService->findByShareToken($token);

        if (!$campagne) {
            throw $this->createNotFoundException('Ce lien de partage n\'existe pas ou a ete revoque');
        }

        // Recuperer les donnees du dashboard
        $kpi = $this->dashboardService->getKpiCampagne($campagne);
        $segments = $this->dashboardService->getProgressionParSegment($campagne);
        $equipe = $this->dashboardService->getStatistiquesEquipe($campagne);

        return $this->render('share/dashboard.html.twig', [
            'campagne' => $campagne,
            'kpi' => $kpi,
            'segments' => $segments,
            'equipe' => $equipe,
            'isSharedView' => true,
        ]);
    }

    /**
     * Generer un lien de partage pour une campagne
     */
    #[Route('/campagne/{id}/share/generate', name: 'app_share_generate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateLink(Campagne $campagne, Request $request): Response
    {
        // CSRF protection
        if (!$this->isCsrfTokenValid('share' . $campagne->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $token = $this->shareService->generateShareToken($campagne);

        // Generer l'URL complete
        $shareUrl = $this->generateUrl('app_share_dashboard', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->addFlash('success', 'Lien de partage genere avec succes');

        // Retourner JSON si requete AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'token' => $token,
                'url' => $shareUrl,
            ]);
        }

        return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
    }

    /**
     * Revoquer un lien de partage
     */
    #[Route('/campagne/{id}/share/revoke', name: 'app_share_revoke', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function revokeLink(Campagne $campagne, Request $request): Response
    {
        // CSRF protection
        if (!$this->isCsrfTokenValid('revoke' . $campagne->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $this->shareService->revokeShareToken($campagne);

        $this->addFlash('success', 'Lien de partage revoque');

        // Retourner JSON si requete AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
    }

    /**
     * Afficher le modal de partage (Turbo Frame)
     */
    #[Route('/campagne/{id}/share/modal', name: 'app_share_modal', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function shareModal(Campagne $campagne): Response
    {
        $shareUrl = null;
        if ($campagne->hasShareLink()) {
            $shareUrl = $this->generateUrl('app_share_dashboard', ['token' => $campagne->getShareToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $this->render('share/_modal.html.twig', [
            'campagne' => $campagne,
            'shareUrl' => $shareUrl,
        ]);
    }
}
