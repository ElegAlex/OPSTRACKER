<?php

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Entity\Document;
use App\Entity\Utilisateur;
use App\Repository\DocumentRepository;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Tests unitaires pour DocumentService
 *
 * T-1005 : Voir la liste des documents
 * T-1006 : Uploader un document (50Mo max)
 * T-1007 : Lier un document a une campagne
 * T-1008 : Supprimer un document
 */
class DocumentServiceTest extends TestCase
{
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&DocumentRepository $documentRepository;
    private DocumentService $documentService;
    private string $uploadDirectory;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->documentRepository = $this->createMock(DocumentRepository::class);
        $this->uploadDirectory = sys_get_temp_dir() . '/opstracker-test-uploads';

        // Creer le repertoire de test
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }

        $this->documentService = new DocumentService(
            $this->entityManager,
            $this->documentRepository,
            new AsciiSlugger(),
            $this->uploadDirectory
        );
    }

    protected function tearDown(): void
    {
        // Nettoyer le repertoire de test
        $this->removeDirectory($this->uploadDirectory);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // =============================================================
    // Tests RG-050 : Extensions autorisees
    // =============================================================

    public function testExtensionPdfAutorisee(): void
    {
        $this->assertTrue(Document::isExtensionAutorisee('pdf'));
        $this->assertTrue(Document::isExtensionAutorisee('PDF'));
    }

    public function testExtensionDocxAutorisee(): void
    {
        $this->assertTrue(Document::isExtensionAutorisee('docx'));
        $this->assertTrue(Document::isExtensionAutorisee('doc'));
    }

    public function testExtensionZipAutorisee(): void
    {
        $this->assertTrue(Document::isExtensionAutorisee('zip'));
    }

    public function testExtensionScriptsAutorisee(): void
    {
        $this->assertTrue(Document::isExtensionAutorisee('ps1'));
        $this->assertTrue(Document::isExtensionAutorisee('bat'));
        $this->assertTrue(Document::isExtensionAutorisee('exe'));
    }

    public function testExtensionNonAutorisee(): void
    {
        $this->assertFalse(Document::isExtensionAutorisee('php'));
        $this->assertFalse(Document::isExtensionAutorisee('js'));
        $this->assertFalse(Document::isExtensionAutorisee('html'));
        $this->assertFalse(Document::isExtensionAutorisee('exe2'));
    }

    // =============================================================
    // Tests RG-050 : Taille max 50 Mo
    // =============================================================

    public function testTailleAutorisee(): void
    {
        // 10 Mo - autorise
        $this->assertTrue(Document::isTailleAutorisee(10 * 1024 * 1024));

        // 50 Mo exactement - autorise
        $this->assertTrue(Document::isTailleAutorisee(50 * 1024 * 1024));
    }

    public function testTailleNonAutorisee(): void
    {
        // 51 Mo - non autorise
        $this->assertFalse(Document::isTailleAutorisee(51 * 1024 * 1024));

        // 100 Mo - non autorise
        $this->assertFalse(Document::isTailleAutorisee(100 * 1024 * 1024));
    }

    public function testConstanteTailleMax(): void
    {
        $this->assertEquals(52428800, Document::TAILLE_MAX_OCTETS); // 50 * 1024 * 1024
    }

    // =============================================================
    // Tests Document Entity
    // =============================================================

    public function testDocumentTypes(): void
    {
        $this->assertArrayHasKey(Document::TYPE_DOCUMENTATION, Document::TYPES);
        $this->assertArrayHasKey(Document::TYPE_SCRIPT, Document::TYPES);
        $this->assertArrayHasKey(Document::TYPE_PROCEDURE, Document::TYPES);
        $this->assertArrayHasKey(Document::TYPE_AUTRE, Document::TYPES);
    }

    public function testDocumentSetType(): void
    {
        $document = new Document();
        $document->setType(Document::TYPE_SCRIPT);

        $this->assertEquals(Document::TYPE_SCRIPT, $document->getType());
        $this->assertEquals('Script', $document->getTypeLabel());
    }

    public function testDocumentSetTypeInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $document = new Document();
        $document->setType('invalid_type');
    }

    public function testDocumentIsScript(): void
    {
        $document = new Document();

        $document->setExtension('ps1');
        $this->assertTrue($document->isScript());

        $document->setExtension('bat');
        $this->assertTrue($document->isScript());

        $document->setExtension('exe');
        $this->assertTrue($document->isScript());

        $document->setExtension('pdf');
        $this->assertFalse($document->isScript());
    }

    public function testDocumentTailleFormatee(): void
    {
        $document = new Document();

        $document->setTaille(500);
        $this->assertEquals('500 o', $document->getTailleFormatee());

        $document->setTaille(1500);
        $this->assertEquals('1.5 Ko', $document->getTailleFormatee());

        $document->setTaille(1500000);
        $this->assertEquals('1.4 Mo', $document->getTailleFormatee());
    }

    public function testDocumentIcone(): void
    {
        $document = new Document();

        $document->setExtension('pdf');
        $this->assertEquals('file-text', $document->getIcone());

        $document->setExtension('ps1');
        $this->assertEquals('terminal', $document->getIcone());

        $document->setExtension('exe');
        $this->assertEquals('cpu', $document->getIcone());

        $document->setExtension('zip');
        $this->assertEquals('archive', $document->getIcone());
    }

    // =============================================================
    // Tests DocumentService
    // =============================================================

    public function testUploadExtensionNonAutorisee(): void
    {
        $campagne = $this->createCampagne();
        $utilisateur = $this->createUtilisateur();

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('php');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension non autorisee');

        $this->documentService->upload($file, $campagne, $utilisateur);
    }

    public function testUploadTailleTropGrande(): void
    {
        $campagne = $this->createCampagne();
        $utilisateur = $this->createUtilisateur();

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('pdf');
        $file->method('getSize')->willReturn(60 * 1024 * 1024); // 60 Mo

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fichier trop volumineux');

        $this->documentService->upload($file, $campagne, $utilisateur);
    }

    public function testGetDocumentsByCampagne(): void
    {
        $campagne = $this->createCampagne();

        $documents = [new Document(), new Document()];
        $this->documentRepository->method('findByCampagne')
            ->with($campagne->getId())
            ->willReturn($documents);

        $result = $this->documentService->getDocumentsByCampagne($campagne);

        $this->assertCount(2, $result);
    }

    public function testGetStatistiques(): void
    {
        $campagne = $this->createCampagne();

        $this->documentRepository->method('countByCampagne')->willReturn(5);
        $this->documentRepository->method('countByTypeForCampagne')->willReturn([
            Document::TYPE_DOCUMENTATION => 3,
            Document::TYPE_SCRIPT => 2,
        ]);
        $this->documentRepository->method('getTailleTotaleByCampagne')->willReturn(10485760); // 10 Mo

        $stats = $this->documentService->getStatistiques($campagne);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(10485760, $stats['taille_totale']);
        $this->assertArrayHasKey('par_type', $stats);
    }

    public function testFormatTaille(): void
    {
        $this->assertEquals('500 o', DocumentService::formatTaille(500));
        $this->assertEquals('1.5 Ko', DocumentService::formatTaille(1536));
        $this->assertEquals('1 Mo', DocumentService::formatTaille(1048576));
        $this->assertEquals('5.5 Mo', DocumentService::formatTaille(5767168));
    }

    // =============================================================
    // Helpers
    // =============================================================

    private function createCampagne(): Campagne
    {
        $campagne = new Campagne();
        $campagne->setNom('Test Campagne');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        // Simuler un ID
        $reflection = new \ReflectionClass($campagne);
        $property = $reflection->getProperty('id');
        $property->setValue($campagne, 1);

        return $campagne;
    }

    private function createUtilisateur(): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail('test@example.com');
        $utilisateur->setNom('Test');
        $utilisateur->setPrenom('User');
        $utilisateur->setPassword('password');

        return $utilisateur;
    }
}
