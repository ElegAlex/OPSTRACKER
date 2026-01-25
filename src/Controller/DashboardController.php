<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Prerequis;
use App\Repository\CampagneRepository;
use App\Repository\PrerequisRepository;
use App\Service\CampagneService;
use App\Service\DashboardService;
use App\Service\PdfExportService;
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
 * - US-604 : Exporter le dashboard en PDF (T-1305)
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
        private readonly CampagneService $campagneService,
        private readonly CampagneRepository $campagneRepository,
        private readonly PrerequisRepository $prerequisRepository,
        private readonly PdfExportService $pdfExportService,
    ) {
    }

    /**
     * T-703 : Dashboard global multi-campagnes.
     * T-1307 : Filtrage par statut de campagne
     * Affiche une vue synthetique de toutes les campagnes actives.
     */
    #[Route('', name: 'app_dashboard_global', methods: ['GET'])]
    public function global(Request $request): Response
    {
        // T-1307 : Recuperer les filtres depuis les query params
        $statutsFilter = $request->query->all('statuts');

        // Si aucun filtre specifie, utiliser null pour les campagnes actives par defaut
        $data = $this->dashboardService->getDashboardGlobal(
            !empty($statutsFilter) ? $statutsFilter : null
        );

        return $this->render('dashboard/global.html.twig', [
            'campagnes' => $data['campagnes'],
            'totaux' => $data['totaux'],
            'filtresActifs' => $statutsFilter,
            'statutsDisponibles' => Campagne::STATUTS,
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

        // Graphique evolution des realisations (14 derniers jours)
        $evolutionData = $this->campagneService->getEvolutionTemporelle($campagne);
        $evolutionOptions = [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]],
            ],
            'plugins' => ['legend' => ['display' => false]],
            'elements' => ['line' => ['tension' => 0.3]],
        ];

        // Stats prerequis pour le widget dashboard
        $prerequisStats = [
            'total' => $this->prerequisRepository->count(['campagne' => $campagne]),
            'fait' => $this->prerequisRepository->count(['campagne' => $campagne, 'statut' => Prerequis::STATUT_FAIT]),
            'en_cours' => $this->prerequisRepository->count(['campagne' => $campagne, 'statut' => Prerequis::STATUT_EN_COURS]),
            'a_faire' => $this->prerequisRepository->count(['campagne' => $campagne, 'statut' => Prerequis::STATUT_A_FAIRE]),
        ];
        $prerequisStats['progression'] = $prerequisStats['total'] > 0
            ? (int) round(($prerequisStats['fait'] / $prerequisStats['total']) * 100)
            : 0;

        return $this->render('dashboard/campagne.html.twig', [
            'campagne' => $campagne,
            'kpi' => $kpi,
            'segments' => $segments,
            'activite' => $activite,
            'equipe' => $equipe,
            'evolutionData' => $evolutionData,
            'evolutionOptions' => $evolutionOptions,
            'prerequisStats' => $prerequisStats,
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

    /**
     * T-1305 : Exporter le dashboard en PDF.
     * US-604 : Format paysage A4, 1 page avec KPIs et progression.
     */
    #[Route('/campagne/{id}/export-pdf', name: 'app_dashboard_export_pdf', methods: ['GET'])]
    public function exportPdf(Campagne $campagne): Response
    {
        $pdfContent = $this->pdfExportService->generateDashboardPdf($campagne);
        $filename = $this->pdfExportService->generateFilename($campagne);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Content-Length' => strlen($pdfContent),
        ]);
    }
}
