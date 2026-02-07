<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Entity\Utilisateur;
use App\Repository\OperationRepository;
use App\Service\ExportCsvService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tests unitaires pour ExportCsvService.
 *
 * User Story : US-906 - Exporter les operations (CSV)
 */
class ExportCsvServiceTest extends TestCase
{
    private OperationRepository&MockObject $operationRepository;
    private ExportCsvService $service;

    protected function setUp(): void
    {
        $this->operationRepository = $this->createMock(OperationRepository::class);
        $this->service = new ExportCsvService($this->operationRepository);
    }

    /**
     * Test export campagne retourne StreamedResponse.
     */
    public function testExportCampagneReturnsStreamedResponse(): void
    {
        $campagne = $this->createCampagne('Test Export');
        $campagne->method('getOperations')->willReturn(new ArrayCollection([]));

        $response = $this->service->exportCampagne($campagne);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    /**
     * Test le nom du fichier genere contient le nom de campagne.
     */
    public function testExportFilenameContainsCampagneName(): void
    {
        $campagne = $this->createCampagne('Migration Windows 11');
        $campagne->method('getOperations')->willReturn(new ArrayCollection([]));

        $response = $this->service->exportCampagne($campagne);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('migration_windows_11', strtolower($disposition));
        $this->assertStringContainsString('.csv', $disposition);
    }

    /**
     * Test export avec colonnes par defaut.
     * RG-015 : Les colonnes par defaut utilisent identifiant/description (donnees personnalisees)
     */
    public function testDefaultColumnsAreDefined(): void
    {
        $expected = ['identifiant', 'description', 'statut', 'segment', 'technicien', 'date_planifiee', 'date_realisation', 'notes'];

        $this->assertSame($expected, array_keys(ExportCsvService::DEFAULT_COLUMNS));
    }

    /**
     * Test export avec colonnes personnalisees.
     */
    public function testExportWithCustomColumns(): void
    {
        $campagne = $this->createCampagne('Test');
        $campagne->method('getOperations')->willReturn(new ArrayCollection([]));

        $columns = ['identifiant', 'description', 'statut'];
        $response = $this->service->exportCampagne($campagne, $columns);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    /**
     * Test export avec filtres.
     */
    public function testExportWithFilters(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->operationRepository
            ->method('createQueryBuilder')
            ->with('o')
            ->willReturn($qb);

        $filters = ['statut' => Operation::STATUT_REALISE];
        $response = $this->service->exportCampagne($campagne, null, $filters);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    /**
     * Test export multiple campagnes.
     */
    public function testExportMultipleCampagnes(): void
    {
        $campagne1 = $this->createCampagne('Campagne 1');
        $campagne1->method('getOperations')->willReturn(new ArrayCollection([]));

        $campagne2 = $this->createCampagne('Campagne 2');
        $campagne2->method('getOperations')->willReturn(new ArrayCollection([]));

        $response = $this->service->exportMultipleCampagnes([$campagne1, $campagne2]);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('export_operations', $response->headers->get('Content-Disposition'));
    }

    /**
     * Test export avec operations reelles.
     */
    public function testExportWithOperations(): void
    {
        $segment = new Segment();
        $segment->setNom('Batiment A');

        $technicien = new Utilisateur();
        $technicien->setPrenom('Karim');
        $technicien->setNom('Martin');
        $technicien->setEmail('karim@demo.opstracker.local');

        $operation = $this->createMock(Operation::class);
        // RG-015 : Les colonnes par defaut utilisent getDisplayIdentifier/getDisplayName
        $operation->method('getDisplayIdentifier')->willReturn('OP-001');
        $operation->method('getDisplayName')->willReturn('Poste Test');
        $operation->method('getStatutLabel')->willReturn('Realise');
        $operation->method('getSegment')->willReturn($segment);
        $operation->method('getTechnicienAssigne')->willReturn($technicien);
        $operation->method('getDatePlanifiee')->willReturn(new \DateTimeImmutable('2026-01-15'));
        $operation->method('getDateRealisation')->willReturn(new \DateTimeImmutable('2026-01-16 10:30'));
        $operation->method('getNotes')->willReturn('Test notes');

        $campagne = $this->createCampagne('Test');
        $campagne->method('getOperations')->willReturn(new ArrayCollection([$operation]));

        $response = $this->service->exportCampagne($campagne);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    /**
     * Test export avec operation sans technicien.
     */
    public function testExportOperationWithoutTechnicien(): void
    {
        $operation = $this->createMock(Operation::class);
        // RG-015 : Les colonnes par defaut utilisent getDisplayIdentifier/getDisplayName
        $operation->method('getDisplayIdentifier')->willReturn('OP-002');
        $operation->method('getDisplayName')->willReturn('Poste Non Assigne');
        $operation->method('getStatutLabel')->willReturn('A planifier');
        $operation->method('getSegment')->willReturn(null);
        $operation->method('getTechnicienAssigne')->willReturn(null);
        $operation->method('getDatePlanifiee')->willReturn(null);
        $operation->method('getDateRealisation')->willReturn(null);
        $operation->method('getNotes')->willReturn(null);

        $campagne = $this->createCampagne('Test');
        $campagne->method('getOperations')->willReturn(new ArrayCollection([$operation]));

        $response = $this->service->exportCampagne($campagne);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    /**
     * Test headers CSV.
     */
    public function testExportHasProperHeaders(): void
    {
        $campagne = $this->createCampagne('Test');
        $campagne->method('getOperations')->willReturn(new ArrayCollection([]));

        $response = $this->service->exportCampagne($campagne);

        $headers = $response->headers;
        $cacheControl = $headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    /**
     * Test slugify avec caracteres speciaux.
     */
    public function testSlugifySpecialCharacters(): void
    {
        $campagne = $this->createCampagne('Campagne été 2026 (test)');
        $campagne->method('getOperations')->willReturn(new ArrayCollection([]));

        $response = $this->service->exportCampagne($campagne);

        $disposition = $response->headers->get('Content-Disposition');
        // Le slug doit etre en minuscules, sans accents ni parentheses
        $this->assertStringContainsString('campagne_ete_2026_test', strtolower($disposition));
    }

    // ========================================
    // Helpers
    // ========================================

    private function createCampagne(string $nom): Campagne&MockObject
    {
        $campagne = $this->createMock(Campagne::class);
        $campagne->method('getNom')->willReturn($nom);
        $campagne->method('getId')->willReturn(1);

        return $campagne;
    }

    private function setEntityId(object $mock, int $id): void
    {
        if (method_exists($mock, 'method')) {
            $mock->method('getId')->willReturn($id);
        }
    }
}
