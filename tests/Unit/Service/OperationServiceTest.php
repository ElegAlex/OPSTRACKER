<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Entity\Utilisateur;
use App\Repository\OperationRepository;
use App\Repository\SegmentRepository;
use App\Service\OperationService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Tests unitaires pour OperationService.
 *
 * Regles metier testees :
 * - RG-017 : Gestion des 6 statuts operation avec workflow
 * - RG-018 : 1 operation = 1 technicien assigne maximum
 * - RG-021 : Motif de report optionnel
 */
class OperationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private OperationRepository&MockObject $operationRepository;
    private SegmentRepository&MockObject $segmentRepository;
    private WorkflowInterface&MockObject $operationWorkflow;
    private OperationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->operationRepository = $this->createMock(OperationRepository::class);
        $this->segmentRepository = $this->createMock(SegmentRepository::class);
        $this->operationWorkflow = $this->createMock(WorkflowInterface::class);

        $this->service = new OperationService(
            $this->entityManager,
            $this->operationRepository,
            $this->segmentRepository,
            $this->operationWorkflow,
        );
    }

    // ==========================================
    // Tests RG-017 : Workflow Operation
    // ==========================================

    public function testAppliquerTransitionPlanifierSuccess(): void
    {
        $operation = $this->createOperation(Operation::STATUT_A_PLANIFIER);

        $this->operationWorkflow
            ->method('can')
            ->with($operation, 'planifier')
            ->willReturn(true);

        $this->operationWorkflow
            ->expects($this->once())
            ->method('apply')
            ->with($operation, 'planifier');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->appliquerTransition($operation, 'planifier');

        $this->assertTrue($result);
    }

    public function testAppliquerTransitionFailedWhenNotAllowed(): void
    {
        $operation = $this->createOperation(Operation::STATUT_A_PLANIFIER);

        $this->operationWorkflow
            ->method('can')
            ->with($operation, 'realiser')
            ->willReturn(false);

        $this->operationWorkflow
            ->expects($this->never())
            ->method('apply');

        $result = $this->service->appliquerTransition($operation, 'realiser');

        $this->assertFalse($result);
    }

    public function testAppliquerTransitionRealiserSetsDateRealisation(): void
    {
        $operation = $this->createOperation(Operation::STATUT_EN_COURS);

        $this->operationWorkflow
            ->method('can')
            ->with($operation, 'realiser')
            ->willReturn(true);

        $this->operationWorkflow
            ->expects($this->once())
            ->method('apply');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->appliquerTransition($operation, 'realiser');

        $this->assertNotNull($operation->getDateRealisation());
        $this->assertInstanceOf(\DateTimeImmutable::class, $operation->getDateRealisation());
    }

    // ==========================================
    // Tests RG-021 : Motif de report
    // ==========================================

    public function testAppliquerTransitionReporterWithMotif(): void
    {
        $operation = $this->createOperation(Operation::STATUT_PLANIFIE);
        $motif = 'Agent absent';

        $this->operationWorkflow
            ->method('can')
            ->with($operation, 'reporter')
            ->willReturn(true);

        $this->operationWorkflow
            ->expects($this->once())
            ->method('apply');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->appliquerTransition($operation, 'reporter', $motif);

        $this->assertSame($motif, $operation->getMotifReport());
    }

    public function testAppliquerTransitionReporterWithoutMotif(): void
    {
        $operation = $this->createOperation(Operation::STATUT_PLANIFIE);

        $this->operationWorkflow
            ->method('can')
            ->with($operation, 'reporter')
            ->willReturn(true);

        $this->operationWorkflow
            ->expects($this->once())
            ->method('apply');

        $this->service->appliquerTransition($operation, 'reporter');

        $this->assertNull($operation->getMotifReport());
    }

    // ==========================================
    // Tests RG-018 : Assignation technicien
    // ==========================================

    public function testAssignerTechnicienSuccess(): void
    {
        $operation = $this->createOperation(Operation::STATUT_PLANIFIE);
        $technicien = $this->createTechnicien();

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->assignerTechnicien($operation, $technicien);

        $this->assertSame($technicien, $operation->getTechnicienAssigne());
    }

    public function testAssignerTechnicienNull(): void
    {
        $operation = $this->createOperation(Operation::STATUT_PLANIFIE);
        $technicien = $this->createTechnicien();
        $operation->setTechnicienAssigne($technicien);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->assignerTechnicien($operation, null);

        $this->assertNull($operation->getTechnicienAssigne());
    }

    public function testAssignerTechnicienFailsForNonTechnicien(): void
    {
        $operation = $this->createOperation(Operation::STATUT_PLANIFIE);

        // Creer un utilisateur qui n'est pas technicien
        $gestionnaire = new Utilisateur();
        $gestionnaire->setEmail('gestionnaire@test.fr');
        $gestionnaire->setNom('Test');
        $gestionnaire->setPrenom('User');
        $gestionnaire->setPassword('password');
        $gestionnaire->setRoles([Utilisateur::ROLE_GESTIONNAIRE]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Seul un technicien peut etre assigne');

        $this->service->assignerTechnicien($operation, $gestionnaire);
    }

    // ==========================================
    // Tests statistiques
    // ==========================================

    public function testGetStatistiquesParStatut(): void
    {
        $campagne = $this->createCampagneWithId(1);

        $counts = [
            Operation::STATUT_A_PLANIFIER => 5,
            Operation::STATUT_PLANIFIE => 10,
            Operation::STATUT_EN_COURS => 3,
            Operation::STATUT_REALISE => 20,
            Operation::STATUT_REPORTE => 2,
            Operation::STATUT_A_REMEDIER => 1,
        ];

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->with(1)
            ->willReturn($counts);

        $stats = $this->service->getStatistiquesParStatut($campagne);

        $this->assertSame(5, $stats[Operation::STATUT_A_PLANIFIER]['count']);
        $this->assertSame('muted', $stats[Operation::STATUT_A_PLANIFIER]['couleur']);
        $this->assertSame('A planifier', $stats[Operation::STATUT_A_PLANIFIER]['label']);
        $this->assertSame('clock', $stats[Operation::STATUT_A_PLANIFIER]['icone']);

        $this->assertSame(20, $stats[Operation::STATUT_REALISE]['count']);
        $this->assertSame('success', $stats[Operation::STATUT_REALISE]['couleur']);
    }

    public function testGetStatistiquesParSegment(): void
    {
        $campagne = $this->createCampagneWithId(1);

        $segment1 = $this->createSegmentWithOperations('Batiment A', [
            $this->createOperation(Operation::STATUT_REALISE),
            $this->createOperation(Operation::STATUT_REALISE),
            $this->createOperation(Operation::STATUT_PLANIFIE),
        ]);
        $this->setEntityId($segment1, 1);

        $segment2 = $this->createSegmentWithOperations('Batiment B', [
            $this->createOperation(Operation::STATUT_REPORTE),
            $this->createOperation(Operation::STATUT_A_REMEDIER),
        ]);
        $this->setEntityId($segment2, 2);

        $this->segmentRepository
            ->method('findByCampagne')
            ->with(1)
            ->willReturn([$segment1, $segment2]);

        $stats = $this->service->getStatistiquesParSegment($campagne);

        // Segment 1 : 2 realises sur 3 = 66.7%
        $this->assertSame(3, $stats[1]['total']);
        $this->assertSame(2, $stats[1]['par_statut'][Operation::STATUT_REALISE]);
        $this->assertEquals(66.7, $stats[1]['progression']);
        $this->assertFalse($stats[1]['en_retard']);

        // Segment 2 : 0 realise sur 2, tous en report/remedier = en retard
        $this->assertSame(2, $stats[2]['total']);
        $this->assertEquals(0, $stats[2]['progression']);
        $this->assertTrue($stats[2]['en_retard']); // > 15% reporte+a_remedier
    }

    public function testGetTransitionsDisponibles(): void
    {
        $operation = $this->createOperation(Operation::STATUT_PLANIFIE);

        $transition1 = new Transition('demarrer', 'planifie', 'en_cours');
        $transition2 = new Transition('reporter', ['planifie', 'en_cours'], 'reporte');

        $this->operationWorkflow
            ->method('getEnabledTransitions')
            ->with($operation)
            ->willReturn([$transition1, $transition2]);

        $transitions = $this->service->getTransitionsDisponibles($operation);

        $this->assertArrayHasKey('demarrer', $transitions);
        $this->assertArrayHasKey('reporter', $transitions);
        $this->assertSame('Demarrer', $transitions['demarrer']);
        $this->assertSame('Reporter', $transitions['reporter']);
    }

    // ==========================================
    // Tests CRUD Segment
    // ==========================================

    public function testCreerSegment(): void
    {
        $campagne = $this->createCampagneWithId(1);

        // Mock de la collection segments vide
        $reflection = new \ReflectionClass($campagne);
        $property = $reflection->getProperty('segments');
        $property->setValue($campagne, new ArrayCollection());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Segment::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $segment = $this->service->creerSegment($campagne, 'Batiment C', 'success');

        $this->assertSame('Batiment C', $segment->getNom());
        $this->assertSame('success', $segment->getCouleur());
        $this->assertSame($campagne, $segment->getCampagne());
        $this->assertSame(1, $segment->getOrdre());
    }

    public function testModifierSegment(): void
    {
        $segment = new Segment();
        $segment->setNom('Ancien nom');
        $segment->setCouleur('primary');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->modifierSegment($segment, 'Nouveau nom', 'success');

        $this->assertSame('Nouveau nom', $result->getNom());
        $this->assertSame('success', $result->getCouleur());
    }

    public function testSupprimerSegment(): void
    {
        $segment = $this->createSegmentWithOperations('Test', [
            $this->createOperation(Operation::STATUT_PLANIFIE),
            $this->createOperation(Operation::STATUT_REALISE),
        ]);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($segment);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->supprimerSegment($segment);

        // Verifier que les operations n'ont plus de segment
        foreach ($segment->getOperations() as $operation) {
            $this->assertNull($operation->getSegment());
        }
    }

    // ==========================================
    // Tests filtrage
    // ==========================================

    public function testGetOperationsWithFilters(): void
    {
        $campagne = $this->createCampagneWithId(1);

        $operations = [
            $this->createOperation(Operation::STATUT_PLANIFIE),
            $this->createOperation(Operation::STATUT_REALISE),
        ];

        $this->operationRepository
            ->method('findWithFilters')
            ->with(1, 'planifie', 5, 10, 'search')
            ->willReturn([$operations[0]]);

        $filtres = [
            'statut' => 'planifie',
            'segment_id' => 5,
            'technicien_id' => 10,
            'search' => 'search',
        ];

        $result = $this->service->getOperationsWithFilters($campagne, $filtres);

        $this->assertCount(1, $result);
    }

    // ==========================================
    // Helper methods
    // ==========================================

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

    private function createTechnicien(): Utilisateur
    {
        $technicien = new Utilisateur();
        $technicien->setEmail('technicien-' . uniqid() . '@test.fr');
        $technicien->setNom('Dupont');
        $technicien->setPrenom('Karim');
        $technicien->setPassword('password');
        $technicien->setRoles([Utilisateur::ROLE_TECHNICIEN]);

        return $technicien;
    }

    private function createCampagneWithId(int $id): Campagne
    {
        $campagne = new Campagne();
        $campagne->setNom('Campagne test');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));
        $this->setEntityId($campagne, $id);

        return $campagne;
    }

    private function createSegmentWithOperations(string $nom, array $operations): Segment
    {
        $segment = new Segment();
        $segment->setNom($nom);
        $segment->setCouleur('primary');

        $reflection = new \ReflectionClass($segment);
        $property = $reflection->getProperty('operations');
        $property->setValue($segment, new ArrayCollection($operations));

        foreach ($operations as $operation) {
            $operation->setSegment($segment);
        }

        return $segment;
    }

    private function setOperationStatut(Operation $operation, string $statut): void
    {
        $reflection = new \ReflectionClass($operation);
        $property = $reflection->getProperty('statut');
        $property->setValue($operation, $statut);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }
}
