<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Repository\CampagneRepository;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour les dashboards de Sophie (Gestionnaire IT).
 *
 * User Stories implementees :
 * - US-601 : Voir le dashboard temps reel (T-701)
 * - US-602 : Voir la progression par segment (T-702)
 * - US-607 : Voir le dashboard global multi-campagnes (T-703)
 *
 * Regles metier :
 * - RG-040 : Affichage temps reel via Turbo Streams
 * - RG-080 : Triple signalisation (icone + couleur + texte)
 * - RG-081 : Contraste RGAA >= 4.5:1
 */
#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly CampagneRepository $campagneRepository,
    ) {
    }

    /**
     * T-703 : Dashboard global multi-campagnes.
     * Affiche une vue synthetique de toutes les campagnes actives.
     */
    #[Route('', name: 'app_dashboard_global', methods: ['GET'])]
    public function global(): Response
    {
        $data = $this->dashboardService->getDashboardGlobal();

        return $this->render('dashboard/global.html.twig', [
            'campagnes' => $data['campagnes'],
            'totaux' => $data['totaux'],
        ]);
    }

    /**
     * T-701 : Dashboard temps reel d'une campagne.
     * Affiche les KPIs, progression par segment, activite recente.
     */
    #[Route('/campagne/{id}', name: 'app_dashboard_campagne', methods: ['GET'])]
    public function campagne(Campagne $campagne): Response
    {
        $kpi = $this->dashboardService->getKpiCampagne($campagne);
        $segments = $this->dashboardService->getProgressionParSegment($campagne);
        $activite = $this->dashboardService->getActiviteRecente($campagne);
        $equipe = $this->dashboardService->getStatistiquesEquipe($campagne);

        return $this->render('dashboard/campagne.html.twig', [
            'campagne' => $campagne,
            'kpi' => $kpi,
            'segments' => $segments,
            'activite' => $activite,
            'equipe' => $equipe,
        ]);
    }

    /**
     * T-702 : Detail de la progression d'un segment.
     */
    #[Route('/campagne/{id}/segment/{segmentId}', name: 'app_dashboard_segment', methods: ['GET'])]
    public function segment(Campagne $campagne, int $segmentId): Response
    {
        $segments = $this->dashboardService->getProgressionParSegment($campagne);
        $segmentData = null;

        foreach ($segments as $s) {
            if ($s['segment']->getId() === $segmentId) {
                $segmentData = $s;
                break;
            }
        }

        if ($segmentData === null) {
            throw $this->createNotFoundException('Segment non trouve');
        }

        return $this->render('dashboard/segment.html.twig', [
            'campagne' => $campagne,
            'segment' => $segmentData,
        ]);
    }

    /**
     * T-704 : Turbo Stream refresh pour temps reel.
     * Renvoie les widgets mis a jour via Turbo Stream.
     */
    #[Route('/campagne/{id}/refresh', name: 'app_dashboard_refresh', methods: ['GET'])]
    public function refresh(Campagne $campagne, Request $request): Response
    {
        $data = $this->dashboardService->getRefreshData($campagne);

        // Si c'est une requete Turbo, retourner des Turbo Streams
        if ($request->headers->get('Accept') === 'text/vnd.turbo-stream.html') {
            return $this->render('dashboard/_turbo_refresh.html.twig', [
                'campagne' => $campagne,
                'kpi' => $data['kpi'],
                'segments' => $data['segments'],
                'activite' => $data['activite'],
                'timestamp' => $data['timestamp'],
            ], new Response('', 200, ['Content-Type' => 'text/vnd.turbo-stream.html']));
        }

        // Sinon retourner du JSON pour polling classique
        return $this->json([
            'kpi' => $data['kpi'],
            'timestamp' => $data['timestamp']->format('c'),
        ]);
    }

    /**
     * T-705 : Turbo Frame pour un widget KPI individuel.
     */
    #[Route('/campagne/{id}/widget/{widget}', name: 'app_dashboard_widget', methods: ['GET'])]
    public function widget(Campagne $campagne, string $widget): Response
    {
        $kpi = $this->dashboardService->getKpiCampagne($campagne);

        if (!isset($kpi[$widget])) {
            throw $this->createNotFoundException('Widget non trouve');
        }

        return $this->render('dashboard/_widget_kpi.html.twig', [
            'campagne' => $campagne,
            'widget' => $widget,
            'data' => $kpi[$widget],
            'total' => $kpi['total'],
        ]);
    }

    /**
     * Turbo Frame pour la section activite recente.
     */
    #[Route('/campagne/{id}/activite', name: 'app_dashboard_activite', methods: ['GET'])]
    public function activite(Campagne $campagne): Response
    {
        $activite = $this->dashboardService->getActiviteRecente($campagne);

        return $this->render('dashboard/_activite.html.twig', [
            'campagne' => $campagne,
            'activite' => $activite,
        ]);
    }

    /**
     * Turbo Frame pour la section segments.
     */
    #[Route('/campagne/{id}/segments', name: 'app_dashboard_segments', methods: ['GET'])]
    public function segments(Campagne $campagne): Response
    {
        $segments = $this->dashboardService->getProgressionParSegment($campagne);

        return $this->render('dashboard/_segments.html.twig', [
            'campagne' => $campagne,
            'segments' => $segments,
        ]);
    }
}
