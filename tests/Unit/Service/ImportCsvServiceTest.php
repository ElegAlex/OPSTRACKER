<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Service\ImportCsvService;
use App\Service\ImportResult;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportCsvServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private ImportCsvService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ImportCsvService(
            $this->entityManager,
            $this->logger,
        );

        $this->tempDir = sys_get_temp_dir() . '/opstracker_tests_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Nettoyer les fichiers temporaires
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testValidateFileWithValidCsv(): void
    {
        $file = $this->createUploadedFile('test.csv', 'text/csv', 1024);
        $result = $this->service->validateFile($file);

        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testValidateFileWithInvalidExtension(): void
    {
        $file = $this->createUploadedFile('test.xlsx', 'application/vnd.ms-excel', 1024);
        $result = $this->service->validateFile($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('.xlsx', $result['error']);
        $this->assertStringContainsString('.csv', $result['error']);
    }

    public function testDetectEncodingUtf8(): void
    {
        $content = "Matricule;Nom;Segment\nPC-001;Jean Dupont;Bâtiment A";
        $filePath = $this->createTempFile($content);

        $encoding = $this->service->detectEncoding($filePath);

        $this->assertSame('UTF-8', $encoding);
    }

    public function testDetectEncodingUtf8WithBom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $content = $bom . "Matricule;Nom;Segment\nPC-001;Jean Dupont;Bâtiment A";
        $filePath = $this->createTempFile($content);

        $encoding = $this->service->detectEncoding($filePath);

        $this->assertSame('UTF-8', $encoding);
    }

    public function testDetectEncodingIso88591(): void
    {
        // Creer un contenu ISO-8859-1 avec des caracteres accentues
        $content = mb_convert_encoding("Matricule;Nom;Segment\nPC-001;Jean Dupont;Bâtiment A", 'ISO-8859-1', 'UTF-8');
        $filePath = $this->createTempFile($content);

        $encoding = $this->service->detectEncoding($filePath);

        $this->assertSame('ISO-8859-1', $encoding);
    }

    public function testDetectSeparatorSemicolon(): void
    {
        $content = "Matricule;Nom;Segment\nPC-001;Jean Dupont;Bâtiment A";
        $filePath = $this->createTempFile($content);

        $separator = $this->service->detectSeparator($filePath, 'UTF-8');

        $this->assertSame(';', $separator);
    }

    public function testDetectSeparatorComma(): void
    {
        $content = "Matricule,Nom,Segment\nPC-001,Jean Dupont,Batiment A";
        $filePath = $this->createTempFile($content);

        $separator = $this->service->detectSeparator($filePath, 'UTF-8');

        $this->assertSame(',', $separator);
    }

    public function testDetectSeparatorTab(): void
    {
        $content = "Matricule\tNom\tSegment\nPC-001\tJean Dupont\tBatiment A";
        $filePath = $this->createTempFile($content);

        $separator = $this->service->detectSeparator($filePath, 'UTF-8');

        $this->assertSame("\t", $separator);
    }

    public function testAnalyzeFile(): void
    {
        $content = "Matricule;Nom;Segment;Notes\n";
        $content .= "PC-001;Jean Dupont;Batiment A;Notes 1\n";
        $content .= "PC-002;Marie Martin;Batiment B;Notes 2\n";
        $content .= "PC-003;Pierre Durand;Batiment A;\n";
        $filePath = $this->createTempFile($content);

        $analysis = $this->service->analyzeFile($filePath);

        $this->assertSame(['Matricule', 'Nom', 'Segment', 'Notes'], $analysis['headers']);
        $this->assertSame(3, $analysis['total_lines']);
        $this->assertSame('UTF-8', $analysis['encoding']);
        $this->assertSame(';', $analysis['separator']);
        $this->assertCount(3, $analysis['preview']);
    }

    public function testAnalyzeFileLimitPreviewTo5(): void
    {
        $content = "Matricule;Nom\n";
        for ($i = 1; $i <= 10; $i++) {
            $content .= "PC-{$i};Nom {$i}\n";
        }
        $filePath = $this->createTempFile($content);

        $analysis = $this->service->analyzeFile($filePath);

        $this->assertSame(10, $analysis['total_lines']);
        $this->assertCount(5, $analysis['preview']); // Limite a 5
    }

    public function testSuggestMappingMatricule(): void
    {
        $headers = ['Code', 'Matricule', 'Nom Agent', 'Batiment'];
        $mapping = $this->service->suggestMapping($headers);

        $this->assertSame(1, $mapping['matricule']); // Index de 'Matricule'
    }

    public function testSuggestMappingNom(): void
    {
        $headers = ['ID', 'Libelle', 'Etage'];
        $mapping = $this->service->suggestMapping($headers);

        $this->assertSame(1, $mapping['nom']); // 'Libelle' contient 'libelle'
    }

    public function testSuggestMappingSegment(): void
    {
        $headers = ['Matricule', 'Nom', 'Batiment', 'Notes'];
        $mapping = $this->service->suggestMapping($headers);

        $this->assertSame(2, $mapping['segment']); // 'Batiment' contient 'batiment'
    }

    public function testSuggestMappingNoMatch(): void
    {
        $headers = ['Col1', 'Col2', 'Col3'];
        $mapping = $this->service->suggestMapping($headers);

        $this->assertNull($mapping['matricule']);
        $this->assertNull($mapping['nom']);
        $this->assertNull($mapping['segment']);
    }

    public function testImportSuccess(): void
    {
        $content = "Matricule;Nom;Segment;Notes\n";
        $content .= "PC-001;Jean Dupont;Batiment A;Notes 1\n";
        $content .= "PC-002;Marie Martin;Batiment B;Notes 2\n";
        $filePath = $this->createTempFile($content);

        $campagne = $this->createCampagne();

        $mapping = [
            'matricule' => 0,
            'nom' => 1,
            'segment' => 2,
            'notes' => 3,
            'date_planifiee' => null,
        ];

        // On s'attend a 2 operations + 2 segments persistes
        $this->entityManager
            ->expects($this->atLeast(2))
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->import($campagne, $filePath, $mapping, [], 'UTF-8', ';');

        $this->assertInstanceOf(ImportResult::class, $result);
        $this->assertSame(2, $result->getImportedCount());
        $this->assertFalse($result->hasErrors());
    }

    public function testImportWithMissingMatricule(): void
    {
        $content = "Matricule;Nom\n";
        $content .= ";Jean Dupont\n"; // Matricule vide
        $content .= "PC-002;Marie Martin\n";
        $filePath = $this->createTempFile($content);

        $campagne = $this->createCampagne();

        $mapping = [
            'matricule' => 0,
            'nom' => 1,
            'segment' => null,
            'notes' => null,
            'date_planifiee' => null,
        ];

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $result = $this->service->import($campagne, $filePath, $mapping, [], 'UTF-8', ';');

        $this->assertSame(1, $result->getImportedCount()); // Seule la 2e ligne
        $this->assertTrue($result->hasErrors());
        $this->assertSame(1, $result->getErrorCount());
    }

    public function testImportWithMissingNom(): void
    {
        $content = "Matricule;Nom\n";
        $content .= "PC-001;\n"; // Nom vide
        $content .= "PC-002;Marie Martin\n";
        $filePath = $this->createTempFile($content);

        $campagne = $this->createCampagne();

        $mapping = [
            'matricule' => 0,
            'nom' => 1,
            'segment' => null,
            'notes' => null,
            'date_planifiee' => null,
        ];

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $result = $this->service->import($campagne, $filePath, $mapping, [], 'UTF-8', ';');

        $this->assertSame(1, $result->getImportedCount());
        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Nom obligatoire', $result->getErrors()[0]['message']);
    }

    public function testImportCreatesSegmentsAutomatically(): void
    {
        $content = "Matricule;Nom;Segment\n";
        $content .= "PC-001;Jean Dupont;Batiment A\n";
        $content .= "PC-002;Marie Martin;Batiment A\n"; // Meme segment
        $content .= "PC-003;Pierre Durand;Batiment B\n"; // Nouveau segment
        $filePath = $this->createTempFile($content);

        $campagne = $this->createCampagne();

        $mapping = [
            'matricule' => 0,
            'nom' => 1,
            'segment' => 2,
            'notes' => null,
            'date_planifiee' => null,
        ];

        $persistedEntities = [];
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->method('flush');

        $result = $this->service->import($campagne, $filePath, $mapping, [], 'UTF-8', ';');

        $this->assertSame(3, $result->getImportedCount());

        // Verifier qu'on a 2 segments crees (Batiment A et Batiment B)
        $segments = array_filter($persistedEntities, fn($e) => $e instanceof Segment);
        $this->assertCount(2, $segments);
    }

    public function testImportWithDate(): void
    {
        $content = "Matricule;Nom;Date\n";
        $content .= "PC-001;Jean Dupont;15/03/2026\n";
        $content .= "PC-002;Marie Martin;2026-04-20\n";
        $filePath = $this->createTempFile($content);

        $campagne = $this->createCampagne();

        $mapping = [
            'matricule' => 0,
            'nom' => 1,
            'segment' => null,
            'notes' => null,
            'date_planifiee' => 2,
        ];

        $persistedOperations = [];
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedOperations) {
                if ($entity instanceof Operation) {
                    $persistedOperations[] = $entity;
                }
            });

        $this->entityManager->method('flush');

        $result = $this->service->import($campagne, $filePath, $mapping, [], 'UTF-8', ';');

        $this->assertSame(2, $result->getImportedCount());
        $this->assertNotNull($persistedOperations[0]->getDatePlanifiee());
        $this->assertNotNull($persistedOperations[1]->getDatePlanifiee());
    }

    public function testImportResultSummary(): void
    {
        $result = new ImportResult();
        $result->incrementImported();
        $result->incrementImported();
        $result->addError(2, 'matricule', 'Matricule manquant');

        $this->assertSame(2, $result->getImportedCount());
        $this->assertSame(1, $result->getErrorCount());
        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->hasErrors());
        // Verifier que le summary contient les chiffres corrects
        $summary = $result->getSummary();
        $this->assertStringContainsString('2', $summary);
        $this->assertStringContainsString('1', $summary);
    }

    public function testImportResultErrorReport(): void
    {
        $result = new ImportResult();
        $result->addError(2, 'matricule', 'Matricule manquant');
        $result->addError(5, 'nom', 'Nom manquant');

        $report = $result->getErrorReport();

        $this->assertStringContainsString('Ligne 2', $report);
        $this->assertStringContainsString('matricule', $report);
        $this->assertStringContainsString('Matricule manquant', $report);
        $this->assertStringContainsString('Ligne 5', $report);
    }

    public function testMaxLinesConstant(): void
    {
        $this->assertSame(100000, ImportCsvService::MAX_LINES);
    }

    public function testAllowedExtensionsConstant(): void
    {
        $this->assertSame(['csv'], ImportCsvService::ALLOWED_EXTENSIONS);
    }

    public function testMappableFieldsContainsRequired(): void
    {
        $fields = ImportCsvService::MAPPABLE_FIELDS;

        $this->assertArrayHasKey('matricule', $fields);
        $this->assertArrayHasKey('nom', $fields);
        $this->assertTrue($fields['matricule']['required']);
        $this->assertTrue($fields['nom']['required']);
        $this->assertFalse($fields['segment']['required']);
    }

    private function createUploadedFile(string $name, string $mimeType, int $size): UploadedFile&MockObject
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')
            ->willReturn(pathinfo($name, PATHINFO_EXTENSION));
        $file->method('getMimeType')
            ->willReturn($mimeType);
        $file->method('getSize')
            ->willReturn($size);

        return $file;
    }

    private function createTempFile(string $content): string
    {
        $filePath = $this->tempDir . '/test_' . uniqid() . '.csv';
        file_put_contents($filePath, $content);
        return $filePath;
    }

    private function createCampagne(): Campagne
    {
        $campagne = new Campagne();
        $campagne->setNom('Test Campagne');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        // Initialiser la collection de segments vide
        $reflection = new \ReflectionClass($campagne);
        $property = $reflection->getProperty('segments');
        $property->setValue($campagne, new ArrayCollection());

        return $campagne;
    }
}
