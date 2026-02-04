<?php

namespace App\Service;

use App\Entity\Campagne;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Service pour l'export PDF des dashboards.
 *
 * User Story : US-604 - Exporter le dashboard en PDF
 *
 * Le PDF genere est en format paysage A4, avec :
 * - Date et titre de la campagne
 * - 4 widgets KPI (Realise, Planifie, Reporte, A remedier)
 * - Progression par segment
 */
class PdfExportService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly DashboardService $dashboardService,
    ) {
    }

    /**
     * Genere le PDF du dashboard d'une campagne
     *
     * @return string Le contenu PDF genere
     */
    public function generateDashboardPdf(Campagne $campagne): string
    {
        // Recuperer les donnees du dashboard
        $kpi = $this->dashboardService->getKpiCampagne($campagne);
        $segments = $this->dashboardService->getProgressionParSegment($campagne);
        $equipe = $this->dashboardService->getStatistiquesEquipe($campagne);

        // Generer le HTML
        $html = $this->twig->render('pdf/dashboard.html.twig', [
            'campagne' => $campagne,
            'kpi' => $kpi,
            'segments' => $segments,
            'equipe' => $equipe,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        // Configurer dompdf
        $options = new Options();
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(true);
        $options->setDefaultFont('sans-serif');
        $options->setDpi(150);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);

        // Format paysage A4
        $dompdf->setPaper('A4', 'landscape');

        // Rendre le PDF
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Genere le nom de fichier pour le PDF
     */
    public function generateFilename(Campagne $campagne): string
    {
        $date = (new \DateTimeImmutable())->format('Y-m-d');
        $slug = $this->slugify($campagne->getNom() ?? 'campagne');

        return sprintf('dashboard_%s_%s.pdf', $slug, $date);
    }

    /**
     * Transforme une chaine en slug
     */
    private function slugify(string $text): string
    {
        // Remplacer les caracteres speciaux
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        if ($transliterated === false) {
            $transliterated = $text;
        }

        // Remplacer les espaces et caracteres non-alphanum par des tirets
        $slug = preg_replace('/[^a-z0-9]+/', '-', $transliterated);
        if (!is_string($slug)) {
            $slug = $transliterated;
        }

        // Supprimer les tirets en debut et fin
        return trim($slug, '-');
    }
}
