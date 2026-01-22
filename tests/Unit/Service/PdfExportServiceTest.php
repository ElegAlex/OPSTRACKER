<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Service\DashboardService;
use App\Service\PdfExportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

/**
 * Tests unitaires pour PdfExportService.
 *
 * User Story : US-604 - Exporter le dashboard en PDF
 */
class PdfExportServiceTest extends TestCase
{
    private Environment&MockObject $twig;
    private DashboardService&MockObject $dashboardService;
    private PdfExportService $service;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->dashboardService = $this->createMock(DashboardService::class);

        $this->service = new PdfExportService(
            $this->twig,
            $this->dashboardService
        );
    }

    /**
     * Test generation du nom de fichier.
     */
    public function testGenerateFilename(): void
    {
        $campagne = $this->createCampagne('Migration Windows 11');

        $filename = $this->service->generateFilename($campagne);

        $this->assertStringStartsWith('dashboard_migration-windows-11_', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertMatchesRegularExpression('/dashboard_migration-windows-11_\d{4}-\d{2}-\d{2}\.pdf/', $filename);
    }

    /**
     * Test generation du nom de fichier avec caracteres speciaux.
     */
    public function testGenerateFilenameWithSpecialCharacters(): void
    {
        $campagne = $this->createCampagne('Déploiement été 2026');

        $filename = $this->service->generateFilename($campagne);

        $this->assertStringContainsString('deploiement-ete-2026', $filename);
        $this->assertStringNotContainsString('é', $filename);
        $this->assertStringNotContainsString('è', $filename);
    }

    /**
     * Test generation du nom de fichier avec espaces multiples.
     */
    public function testGenerateFilenameWithMultipleSpaces(): void
    {
        $campagne = $this->createCampagne('Test   Multiple   Spaces');

        $filename = $this->service->generateFilename($campagne);

        // Les espaces multiples doivent etre reduits a un seul tiret
        $this->assertStringContainsString('test-multiple-spaces', $filename);
        $this->assertStringNotContainsString('--', $filename);
    }

    /**
     * Test generation PDF appelle les bonnes methodes du DashboardService.
     */
    public function testGenerateDashboardPdfCallsDashboardService(): void
    {
        $campagne = $this->createCampagne('Test');

        $kpiData = [
            'total' => 100,
            'realise' => ['count' => 50, 'percentage' => 50.0, 'icon' => 'check', 'color' => 'success', 'label' => 'Realise'],
            'planifie' => ['count' => 30, 'percentage' => 30.0, 'icon' => 'clock', 'color' => 'primary', 'label' => 'Planifie'],
            'reporte' => ['count' => 10, 'percentage' => 10.0, 'icon' => 'pause', 'color' => 'warning', 'label' => 'Reporte'],
            'a_remedier' => ['count' => 10, 'percentage' => 10.0, 'icon' => 'alert', 'color' => 'danger', 'label' => 'A remedier'],
            'en_cours' => ['count' => 0, 'percentage' => 0.0, 'icon' => 'play', 'color' => 'primary', 'label' => 'En cours'],
            'a_planifier' => ['count' => 0, 'percentage' => 0.0, 'icon' => 'calendar', 'color' => 'muted', 'label' => 'A planifier'],
        ];

        $this->dashboardService
            ->expects($this->once())
            ->method('getKpiCampagne')
            ->with($campagne)
            ->willReturn($kpiData);

        $this->dashboardService
            ->expects($this->once())
            ->method('getProgressionParSegment')
            ->with($campagne)
            ->willReturn([]);

        $this->dashboardService
            ->expects($this->once())
            ->method('getStatistiquesEquipe')
            ->with($campagne)
            ->willReturn([]);

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with(
                'pdf/dashboard.html.twig',
                $this->callback(function ($params) use ($campagne) {
                    return $params['campagne'] === $campagne
                        && isset($params['kpi'])
                        && isset($params['segments'])
                        && isset($params['equipe'])
                        && $params['generatedAt'] instanceof \DateTimeImmutable;
                })
            )
            ->willReturn('<html><body>Test PDF</body></html>');

        $pdfContent = $this->service->generateDashboardPdf($campagne);

        // DomPDF retourne du contenu binaire
        $this->assertNotEmpty($pdfContent);
        // Verifier que c'est du PDF (magic bytes)
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    /**
     * Test generation PDF avec campagne vide.
     */
    public function testGenerateDashboardPdfWithEmptyCampagne(): void
    {
        $campagne = $this->createCampagne('Campagne Vide');

        $this->dashboardService
            ->method('getKpiCampagne')
            ->willReturn([
                'total' => 0,
                'realise' => ['count' => 0, 'percentage' => 0.0, 'icon' => 'check', 'color' => 'success', 'label' => 'Realise'],
                'planifie' => ['count' => 0, 'percentage' => 0.0, 'icon' => 'clock', 'color' => 'primary', 'label' => 'Planifie'],
                'reporte' => ['count' => 0, 'percentage' => 0.0, 'icon' => 'pause', 'color' => 'warning', 'label' => 'Reporte'],
                'a_remedier' => ['count' => 0, 'percentage' => 0.0, 'icon' => 'alert', 'color' => 'danger', 'label' => 'A remedier'],
                'en_cours' => ['count' => 0, 'percentage' => 0.0, 'icon' => 'play', 'color' => 'primary', 'label' => 'En cours'],
                'a_planifier' => ['count' => 0, 'percentage' => 0.0, 'icon' => 'calendar', 'color' => 'muted', 'label' => 'A planifier'],
            ]);

        $this->dashboardService
            ->method('getProgressionParSegment')
            ->willReturn([]);

        $this->dashboardService
            ->method('getStatistiquesEquipe')
            ->willReturn([]);

        $this->twig
            ->method('render')
            ->willReturn('<html><body>Empty Dashboard</body></html>');

        $pdfContent = $this->service->generateDashboardPdf($campagne);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    /**
     * Test format du nom de fichier avec date.
     */
    public function testFilenameContainsCurrentDate(): void
    {
        $campagne = $this->createCampagne('Test Date');
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $filename = $this->service->generateFilename($campagne);

        $this->assertStringContainsString($today, $filename);
    }

    // ========================================
    // Helpers
    // ========================================

    private function createCampagne(string $nom): Campagne
    {
        $campagne = new Campagne();
        $campagne->setNom($nom);
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        return $campagne;
    }
}
