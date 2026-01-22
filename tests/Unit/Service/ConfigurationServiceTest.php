<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\ChecklistTemplate;
use App\Entity\Segment;
use App\Entity\TypeOperation;
use App\Repository\ChecklistTemplateRepository;
use App\Repository\SegmentRepository;
use App\Repository\TypeOperationRepository;
use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests unitaires pour ConfigurationService.
 *
 * Regles metier testees :
 * - RG-100 : Export en ZIP (types_operations.csv, templates_checklists.csv, config_metadata.json)
 * - RG-101 : Import avec gestion des conflits
 * - RG-102 : Compatibilite entre versions
 */
class ConfigurationServiceTest extends TestCase
{
    private TypeOperationRepository&MockObject $typeOperationRepository;
    private ChecklistTemplateRepository&MockObject $checklistTemplateRepository;
    private SegmentRepository&MockObject $segmentRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private ConfigurationService $service;

    protected function setUp(): void
    {
        $this->typeOperationRepository = $this->createMock(TypeOperationRepository::class);
        $this->checklistTemplateRepository = $this->createMock(ChecklistTemplateRepository::class);
        $this->segmentRepository = $this->createMock(SegmentRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new ConfigurationService(
            $this->typeOperationRepository,
            $this->checklistTemplateRepository,
            $this->segmentRepository,
            $this->entityManager
        );
    }

    /**
     * Test export genere un fichier ZIP.
     */
    public function testExporterCreatesZipFile(): void
    {
        $this->typeOperationRepository
            ->method('findAll')
            ->willReturn([]);

        $this->checklistTemplateRepository
            ->method('findAll')
            ->willReturn([]);

        $this->segmentRepository
            ->method('findAll')
            ->willReturn([]);

        $this->typeOperationRepository
            ->method('count')
            ->willReturn(0);

        $this->checklistTemplateRepository
            ->method('count')
            ->willReturn(0);

        $zipPath = $this->service->exporter();

        $this->assertFileExists($zipPath);
        $this->assertStringEndsWith('.zip', $zipPath);

        // Verifier le contenu du ZIP
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('types_operations.csv'));
        $this->assertNotFalse($zip->locateName('templates_checklists.csv'));
        $this->assertNotFalse($zip->locateName('config_metadata.json'));
        $zip->close();

        // Nettoyer
        unlink($zipPath);
    }

    /**
     * Test export avec types d'operation.
     */
    public function testExporterWithTypeOperations(): void
    {
        $type1 = new TypeOperation();
        $type1->setNom('Migration');
        $type1->setIcone('refresh');
        $type1->setCouleur('primary');
        $type1->setActif(true);

        $type2 = new TypeOperation();
        $type2->setNom('Installation');
        $type2->setIcone('download');
        $type2->setCouleur('success');
        $type2->setActif(true);

        $this->typeOperationRepository
            ->method('findAll')
            ->willReturn([$type1, $type2]);

        $this->checklistTemplateRepository
            ->method('findAll')
            ->willReturn([]);

        $this->segmentRepository
            ->method('findAll')
            ->willReturn([]);

        $this->typeOperationRepository
            ->method('count')
            ->willReturn(2);

        $this->checklistTemplateRepository
            ->method('count')
            ->willReturn(0);

        $zipPath = $this->service->exporter();

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $typesCsv = $zip->getFromName('types_operations.csv');
        $zip->close();

        $this->assertStringContainsString('Migration', $typesCsv);
        $this->assertStringContainsString('Installation', $typesCsv);
        $this->assertStringContainsString('refresh', $typesCsv);

        unlink($zipPath);
    }

    /**
     * Test export avec templates de checklist.
     */
    public function testExporterWithTemplates(): void
    {
        $template = new ChecklistTemplate();
        $template->setNom('Checklist Migration');
        $template->setVersion(1);
        $template->setActif(true);
        $template->setEtapes([
            'phases' => [
                ['id' => 'phase-1', 'nom' => 'Preparation', 'etapes' => [
                    ['id' => 'etape-1', 'titre' => 'Sauvegarde', 'obligatoire' => true],
                    ['id' => 'etape-2', 'titre' => 'Verification', 'obligatoire' => true],
                ]],
            ],
        ]);

        $this->typeOperationRepository
            ->method('findAll')
            ->willReturn([]);

        $this->checklistTemplateRepository
            ->method('findAll')
            ->willReturn([$template]);

        $this->segmentRepository
            ->method('findAll')
            ->willReturn([]);

        $this->typeOperationRepository
            ->method('count')
            ->willReturn(0);

        $this->checklistTemplateRepository
            ->method('count')
            ->willReturn(1);

        $zipPath = $this->service->exporter();

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $templatesCsv = $zip->getFromName('templates_checklists.csv');
        $zip->close();

        $this->assertStringContainsString('Checklist Migration', $templatesCsv);
        $this->assertStringContainsString('Sauvegarde', $templatesCsv);

        unlink($zipPath);
    }

    /**
     * Test metadonnees contiennent la version.
     */
    public function testExporterIncludesMetadata(): void
    {
        $this->typeOperationRepository
            ->method('findAll')
            ->willReturn([]);

        $this->checklistTemplateRepository
            ->method('findAll')
            ->willReturn([]);

        $this->segmentRepository
            ->method('findAll')
            ->willReturn([]);

        $this->typeOperationRepository
            ->method('count')
            ->willReturn(0);

        $this->checklistTemplateRepository
            ->method('count')
            ->willReturn(0);

        $zipPath = $this->service->exporter();

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $metadataJson = $zip->getFromName('config_metadata.json');
        $zip->close();

        $metadata = json_decode($metadataJson, true);
        $this->assertSame('1.0.0', $metadata['version']);
        $this->assertSame('OpsTracker', $metadata['application']);
        $this->assertArrayHasKey('exported_at', $metadata);
        $this->assertArrayHasKey('counts', $metadata);

        unlink($zipPath);
    }

    /**
     * Test les modes d'import disponibles.
     */
    public function testImportModesAvailable(): void
    {
        $this->assertSame('remplacer', ConfigurationService::MODE_REMPLACER);
        $this->assertSame('ignorer', ConfigurationService::MODE_IGNORER);
        $this->assertSame('creer_nouveaux', ConfigurationService::MODE_CREER_NOUVEAUX);

        $this->assertCount(3, ConfigurationService::MODES);
    }

    /**
     * Test import ZIP invalide.
     */
    public function testImporterWithInvalidZip(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'not a zip file');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'invalid.zip',
            'application/zip',
            null,
            true
        );

        $result = $this->service->importer($uploadedFile);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);

        unlink($tempFile);
    }

    /**
     * Test import ZIP sans metadata.
     */
    public function testImporterWithoutMetadata(): void
    {
        // Creer un ZIP sans config_metadata.json
        $tempDir = sys_get_temp_dir() . '/test_config_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/types_operations.csv', "nom,description\nTest,Test desc");

        $zipPath = $tempDir . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFile($tempDir . '/types_operations.csv', 'types_operations.csv');
        $zip->close();

        $uploadedFile = new UploadedFile(
            $zipPath,
            'config.zip',
            'application/zip',
            null,
            true
        );

        $result = $this->service->importer($uploadedFile);

        $this->assertFalse($result['success']);
        $this->assertContains('Fichier config_metadata.json manquant.', $result['errors']);

        // Nettoyer
        unlink($tempDir . '/types_operations.csv');
        rmdir($tempDir);
        unlink($zipPath);
    }

    /**
     * Test analyse d'un fichier ZIP.
     */
    public function testAnalyserValidZip(): void
    {
        // Creer un ZIP valide
        $tempDir = sys_get_temp_dir() . '/test_analyse_' . uniqid();
        mkdir($tempDir);

        file_put_contents($tempDir . '/types_operations.csv', "nom,description\nType1,Desc1\nType2,Desc2");
        file_put_contents($tempDir . '/templates_checklists.csv', "nom,version\nTemplate1,1");
        file_put_contents($tempDir . '/config_metadata.json', json_encode([
            'version' => '1.0.0',
            'application' => 'OpsTracker',
            'exported_at' => '2026-01-22T10:00:00+00:00',
        ]));

        $zipPath = $tempDir . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        foreach (glob($tempDir . '/*') as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        $uploadedFile = new UploadedFile(
            $zipPath,
            'config.zip',
            'application/zip',
            null,
            true
        );

        $result = $this->service->analyser($uploadedFile);

        $this->assertTrue($result['valid']);
        $this->assertSame('1.0.0', $result['metadata']['version']);
        $this->assertSame(2, $result['content']['types_operations']);
        $this->assertSame(1, $result['content']['templates_checklists']);

        // Nettoyer
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);
        unlink($zipPath);
    }

    /**
     * Test analyse ZIP invalide.
     */
    public function testAnalyserInvalidZip(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'invalid content');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'invalid.zip',
            'application/zip',
            null,
            true
        );

        $result = $this->service->analyser($uploadedFile);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);

        unlink($tempFile);
    }

    /**
     * Test export des segments.
     */
    public function testExporterWithSegments(): void
    {
        $segment1 = new Segment();
        $segment1->setNom('Batiment A');
        $segment1->setCouleur('primary');

        $segment2 = new Segment();
        $segment2->setNom('Batiment B');
        $segment2->setCouleur('success');

        $this->typeOperationRepository
            ->method('findAll')
            ->willReturn([]);

        $this->checklistTemplateRepository
            ->method('findAll')
            ->willReturn([]);

        $this->segmentRepository
            ->method('findAll')
            ->willReturn([$segment1, $segment2]);

        $this->segmentRepository
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($segment1, $segment2) {
                if ($criteria['nom'] === 'Batiment A') {
                    return $segment1;
                }
                if ($criteria['nom'] === 'Batiment B') {
                    return $segment2;
                }
                return null;
            });

        $this->typeOperationRepository
            ->method('count')
            ->willReturn(0);

        $this->checklistTemplateRepository
            ->method('count')
            ->willReturn(0);

        $zipPath = $this->service->exporter();

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $segmentsCsv = $zip->getFromName('segments.csv');
        $zip->close();

        $this->assertStringContainsString('Batiment A', $segmentsCsv);
        $this->assertStringContainsString('Batiment B', $segmentsCsv);
        $this->assertStringContainsString('primary', $segmentsCsv);

        unlink($zipPath);
    }
}
