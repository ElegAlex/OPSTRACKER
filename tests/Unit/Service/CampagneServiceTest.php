<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Entity\ChecklistTemplate;
use App\Entity\Operation;
use App\Entity\TypeOperation;
use App\Repository\CampagneRepository;
use App\Service\CampagneService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\WorkflowInterface;

class CampagneServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CampagneRepository&MockObject $campagneRepository;
    private WorkflowInterface&MockObject $campagneWorkflow;
    private CampagneService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->campagneRepository = $this->createMock(CampagneRepository::class);
        $this->campagneWorkflow = $this->createMock(WorkflowInterface::class);

        $this->service = new CampagneService(
            $this->entityManager,
            $this->campagneRepository,
            $this->campagneWorkflow,
        );
    }

    public function testGetCampagnesGroupedByStatut(): void
    {
        $campagne1 = $this->createCampagne('Campagne 1', Campagne::STATUT_EN_COURS);
        $campagne2 = $this->createCampagne('Campagne 2', Campagne::STATUT_TERMINEE);

        $grouped = [
            Campagne::STATUT_PREPARATION => [],
            Campagne::STATUT_A_VENIR => [],
            Campagne::STATUT_EN_COURS => [$campagne1],
            Campagne::STATUT_TERMINEE => [$campagne2],
            Campagne::STATUT_ARCHIVEE => [],
        ];

        $this->campagneRepository
            ->method('findAllGroupedByStatut')
            ->willReturn($grouped);

        $result = $this->service->getCampagnesGroupedByStatut();

        $this->assertArrayHasKey(Campagne::STATUT_EN_COURS, $result);
        $this->assertCount(1, $result[Campagne::STATUT_EN_COURS]['campagnes']);
        $this->assertSame(1, $result[Campagne::STATUT_EN_COURS]['count']);
        $this->assertSame('En cours', $result[Campagne::STATUT_EN_COURS]['label']);
        $this->assertSame('success', $result[Campagne::STATUT_EN_COURS]['couleur']);
    }

    public function testCreerCampagne(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Campagne::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $dateDebut = new \DateTimeImmutable('2026-01-15');
        $dateFin = new \DateTimeImmutable('2026-02-28');

        $campagne = $this->service->creerCampagne(
            'Migration Windows 11',
            $dateDebut,
            $dateFin,
            'Description de la campagne'
        );

        $this->assertSame('Migration Windows 11', $campagne->getNom());
        $this->assertSame($dateDebut, $campagne->getDateDebut());
        $this->assertSame($dateFin, $campagne->getDateFin());
        $this->assertSame('Description de la campagne', $campagne->getDescription());
        $this->assertSame(Campagne::STATUT_PREPARATION, $campagne->getStatut());
    }

    public function testConfigurerWorkflow(): void
    {
        $campagne = new Campagne();
        $campagne->setNom('Test');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        $typeOperation = new TypeOperation();
        $typeOperation->setNom('Migration poste');

        $checklistTemplate = new ChecklistTemplate();
        $checklistTemplate->setNom('Checklist migration');
        $checklistTemplate->setVersion(1);
        $checklistTemplate->setEtapes([]);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->configurerWorkflow($campagne, $typeOperation, $checklistTemplate);

        $this->assertSame($typeOperation, $result->getTypeOperation());
        $this->assertSame($checklistTemplate, $result->getChecklistTemplate());
    }

    public function testAppliquerTransitionSuccess(): void
    {
        $campagne = $this->createCampagne('Test', Campagne::STATUT_PREPARATION);

        $this->campagneWorkflow
            ->method('can')
            ->with($campagne, 'valider')
            ->willReturn(true);

        $this->campagneWorkflow
            ->expects($this->once())
            ->method('apply')
            ->with($campagne, 'valider');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->appliquerTransition($campagne, 'valider');

        $this->assertTrue($result);
    }

    public function testAppliquerTransitionFailed(): void
    {
        $campagne = $this->createCampagne('Test', Campagne::STATUT_PREPARATION);

        $this->campagneWorkflow
            ->method('can')
            ->with($campagne, 'terminer')
            ->willReturn(false);

        $this->campagneWorkflow
            ->expects($this->never())
            ->method('apply');

        $result = $this->service->appliquerTransition($campagne, 'terminer');

        $this->assertFalse($result);
    }

    public function testGetStatistiquesCampagneEmpty(): void
    {
        $campagne = $this->createCampagneWithOperations('Test', []);

        $stats = $this->service->getStatistiquesCampagne($campagne);

        $this->assertSame(0, $stats['total']);
        $this->assertEquals(0, $stats['progression']);
        $this->assertSame(0, $stats['realises']);
    }

    public function testGetStatistiquesCampagneWithOperations(): void
    {
        $operations = [
            $this->createOperation(Operation::STATUT_REALISE),
            $this->createOperation(Operation::STATUT_REALISE),
            $this->createOperation(Operation::STATUT_PLANIFIE),
            $this->createOperation(Operation::STATUT_REPORTE),
        ];

        $campagne = $this->createCampagneWithOperations('Test', $operations);

        $stats = $this->service->getStatistiquesCampagne($campagne);

        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['realises']);
        $this->assertSame(50.0, $stats['progression']);
        $this->assertSame(2, $stats['par_statut'][Operation::STATUT_REALISE]);
        $this->assertSame(1, $stats['par_statut'][Operation::STATUT_PLANIFIE]);
        $this->assertSame(1, $stats['par_statut'][Operation::STATUT_REPORTE]);
    }

    public function testGetStatistiquesGlobalesEmpty(): void
    {
        $this->campagneRepository
            ->method('findAll')
            ->willReturn([]);

        $stats = $this->service->getStatistiquesGlobales();

        $this->assertSame(0, $stats['total_campagnes']);
        $this->assertSame(0, $stats['actives']);
        $this->assertSame(0, $stats['terminees']);
        $this->assertSame(0, $stats['archivees']);
        $this->assertSame(0, $stats['total_operations']);
    }

    public function testGetStatistiquesGlobalesWithCampagnes(): void
    {
        $campagne1 = $this->createCampagneWithOperations('Active 1', [
            $this->createOperation(Operation::STATUT_REALISE),
            $this->createOperation(Operation::STATUT_PLANIFIE),
        ]);
        $this->setCampagneStatut($campagne1, Campagne::STATUT_EN_COURS);

        $campagne2 = $this->createCampagneWithOperations('Terminee', [
            $this->createOperation(Operation::STATUT_REALISE),
        ]);
        $this->setCampagneStatut($campagne2, Campagne::STATUT_TERMINEE);

        $campagne3 = $this->createCampagneWithOperations('Archivee', [
            $this->createOperation(Operation::STATUT_REALISE),
            $this->createOperation(Operation::STATUT_REALISE),
        ]);
        $this->setCampagneStatut($campagne3, Campagne::STATUT_ARCHIVEE);

        $this->campagneRepository
            ->method('findAll')
            ->willReturn([$campagne1, $campagne2, $campagne3]);

        $stats = $this->service->getStatistiquesGlobales();

        $this->assertSame(3, $stats['total_campagnes']);
        $this->assertSame(1, $stats['actives']); // en_cours
        $this->assertSame(1, $stats['terminees']);
        $this->assertSame(1, $stats['archivees']);
        $this->assertSame(5, $stats['total_operations']);
        $this->assertSame(3, $stats['operations_actives']); // non archivees
        $this->assertSame(2, $stats['operations_realisees']); // non archivees
    }

    private function createCampagne(string $nom, string $statut = Campagne::STATUT_PREPARATION): Campagne
    {
        $campagne = new Campagne();
        $campagne->setNom($nom);
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));
        $this->setCampagneStatut($campagne, $statut);

        return $campagne;
    }

    private function createCampagneWithOperations(string $nom, array $operations): Campagne
    {
        $campagne = $this->createCampagne($nom);

        // Utiliser la reflection pour acceder aux operations
        $reflection = new \ReflectionClass($campagne);
        $property = $reflection->getProperty('operations');
        $property->setValue($campagne, new ArrayCollection($operations));

        return $campagne;
    }

    private function createOperation(string $statut): Operation
    {
        // RG-015 : matricule et nom sont dans donneesPersonnalisees
        $operation = new Operation();
        $operation->setDonneesPersonnalisees([
            'matricule' => 'MAT-' . uniqid(),
            'nom' => 'Operation test',
        ]);
        $this->setOperationStatut($operation, $statut);

        return $operation;
    }

    private function setCampagneStatut(Campagne $campagne, string $statut): void
    {
        $reflection = new \ReflectionClass($campagne);
        $property = $reflection->getProperty('statut');
        $property->setValue($campagne, $statut);
    }

    private function setOperationStatut(Operation $operation, string $statut): void
    {
        $reflection = new \ReflectionClass($operation);
        $property = $reflection->getProperty('statut');
        $property->setValue($operation, $statut);
    }
}
