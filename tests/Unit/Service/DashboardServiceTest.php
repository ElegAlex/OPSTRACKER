<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Repository\CampagneRepository;
use App\Repository\OperationRepository;
use App\Repository\SegmentRepository;
use App\Service\DashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour DashboardService.
 *
 * Regles metier testees :
 * - RG-040 : Affichage temps reel via Turbo Streams
 * - RG-080 : Triple signalisation (icone + couleur + texte)
 */
class DashboardServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CampagneRepository&MockObject $campagneRepository;
    private OperationRepository&MockObject $operationRepository;
    private SegmentRepository&MockObject $segmentRepository;
    private DashboardService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->campagneRepository = $this->createMock(CampagneRepository::class);
        $this->operationRepository = $this->createMock(OperationRepository::class);
        $this->segmentRepository = $this->createMock(SegmentRepository::class);

        $this->service = new DashboardService(
            $this->entityManager,
            $this->campagneRepository,
            $this->operationRepository,
            $this->segmentRepository,
        );
    }

    /**
     * T-705 : Test des KPIs d'une campagne vide.
     */
    public function testGetKpiCampagneEmpty(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->with(1)
            ->willReturn([
                Operation::STATUT_A_PLANIFIER => 0,
                Operation::STATUT_PLANIFIE => 0,
                Operation::STATUT_EN_COURS => 0,
                Operation::STATUT_REALISE => 0,
                Operation::STATUT_REPORTE => 0,
                Operation::STATUT_A_REMEDIER => 0,
            ]);

        $this->mockEntityManagerWithScalarResult(0);

        $kpi = $this->service->getKpiCampagne($campagne);

        $this->assertSame(0, $kpi['total']);
        $this->assertSame(0, $kpi['realise']['count']);
        $this->assertSame(0.0, $kpi['realise']['percentage']);
        $this->assertSame('check-circle', $kpi['realise']['icon']);
        $this->assertSame('success', $kpi['realise']['color']);
        $this->assertSame('Réalisé', $kpi['realise']['label']);
    }

    /**
     * T-705 : Test des KPIs avec operations.
     */
    public function testGetKpiCampagneWithOperations(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->with(1)
            ->willReturn([
                Operation::STATUT_A_PLANIFIER => 10,
                Operation::STATUT_PLANIFIE => 20,
                Operation::STATUT_EN_COURS => 5,
                Operation::STATUT_REALISE => 50,
                Operation::STATUT_REPORTE => 10,
                Operation::STATUT_A_REMEDIER => 5,
            ]);

        $this->mockEntityManagerWithScalarResult(5);

        $kpi = $this->service->getKpiCampagne($campagne);

        $this->assertSame(100, $kpi['total']);
        $this->assertSame(50, $kpi['realise']['count']);
        $this->assertSame(50.0, $kpi['realise']['percentage']);
        $this->assertSame(5, $kpi['realise']['today']);

        // Planifie = planifie + en_cours
        $this->assertSame(25, $kpi['planifie']['count']);
        $this->assertSame(25.0, $kpi['planifie']['percentage']);

        $this->assertSame(10, $kpi['reporte']['count']);
        $this->assertSame(10.0, $kpi['reporte']['percentage']);

        $this->assertSame(5, $kpi['a_remedier']['count']);
        $this->assertSame(5.0, $kpi['a_remedier']['percentage']);
    }

    /**
     * T-705 : Test triple signalisation RG-080 - tous les widgets ont icone + couleur + label.
     */
    public function testKpiTripleSignalisation(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->willReturn([
                Operation::STATUT_A_PLANIFIER => 0,
                Operation::STATUT_PLANIFIE => 0,
                Operation::STATUT_EN_COURS => 0,
                Operation::STATUT_REALISE => 0,
                Operation::STATUT_REPORTE => 0,
                Operation::STATUT_A_REMEDIER => 0,
            ]);

        $this->mockEntityManagerWithScalarResult(0);

        $kpi = $this->service->getKpiCampagne($campagne);

        // Verifier que chaque widget a les 3 elements de signalisation
        $widgets = ['realise', 'planifie', 'reporte', 'a_remedier', 'en_cours', 'a_planifier'];

        foreach ($widgets as $widget) {
            $this->assertArrayHasKey('icon', $kpi[$widget], "Widget $widget doit avoir une icone");
            $this->assertArrayHasKey('color', $kpi[$widget], "Widget $widget doit avoir une couleur");
            $this->assertArrayHasKey('label', $kpi[$widget], "Widget $widget doit avoir un label");
            $this->assertNotEmpty($kpi[$widget]['icon'], "Icone du widget $widget ne doit pas etre vide");
            $this->assertNotEmpty($kpi[$widget]['color'], "Couleur du widget $widget ne doit pas etre vide");
            $this->assertNotEmpty($kpi[$widget]['label'], "Label du widget $widget ne doit pas etre vide");
        }
    }

    /**
     * T-702 : Test progression par segment vide.
     */
    public function testGetProgressionParSegmentEmpty(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $this->segmentRepository
            ->method('findByCampagne')
            ->with(1)
            ->willReturn([]);

        $result = $this->service->getProgressionParSegment($campagne);

        $this->assertCount(0, $result);
    }

    /**
     * T-702 : Test progression par segment avec donnees.
     */
    public function testGetProgressionParSegmentWithData(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $segment = new Segment();
        $segment->setNom('Batiment A');
        $this->setEntityId($segment, 1);

        $this->segmentRepository
            ->method('findByCampagne')
            ->with(1)
            ->willReturn([$segment]);

        // Mock pour countByStatutForSegment via EntityManager
        $this->mockEntityManagerWithArrayResult([
            ['statut' => Operation::STATUT_REALISE, 'total' => 80],
            ['statut' => Operation::STATUT_PLANIFIE, 'total' => 10],
            ['statut' => Operation::STATUT_EN_COURS, 'total' => 5],
            ['statut' => Operation::STATUT_REPORTE, 'total' => 3],
            ['statut' => Operation::STATUT_A_REMEDIER, 'total' => 2],
        ]);

        $result = $this->service->getProgressionParSegment($campagne);

        $this->assertCount(1, $result);
        $this->assertSame($segment, $result[0]['segment']);
        $this->assertSame(100, $result[0]['total']);
        $this->assertSame(80, $result[0]['realise']);
        $this->assertSame(80.0, $result[0]['progression']);
        $this->assertFalse($result[0]['is_late']);
    }

    /**
     * T-702 : Test detection segment en retard.
     */
    public function testGetProgressionParSegmentDetectsLateSegment(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $segment = new Segment();
        $segment->setNom('Batiment problematique');
        $this->setEntityId($segment, 1);

        $this->segmentRepository
            ->method('findByCampagne')
            ->willReturn([$segment]);

        // Segment en retard : <50% progression et problemes
        $this->mockEntityManagerWithArrayResult([
            ['statut' => Operation::STATUT_REALISE, 'total' => 20],
            ['statut' => Operation::STATUT_PLANIFIE, 'total' => 30],
            ['statut' => Operation::STATUT_REPORTE, 'total' => 30],
            ['statut' => Operation::STATUT_A_REMEDIER, 'total' => 20],
        ]);

        $result = $this->service->getProgressionParSegment($campagne);

        $this->assertTrue($result[0]['is_late']);
        $this->assertSame(20.0, $result[0]['progression']);
    }

    /**
     * T-703 : Test dashboard global vide.
     */
    public function testGetDashboardGlobalEmpty(): void
    {
        $this->campagneRepository
            ->method('findBy')
            ->willReturn([]);

        $result = $this->service->getDashboardGlobal();

        $this->assertCount(0, $result['campagnes']);
        $this->assertSame(0, $result['totaux']['campagnes']);
        $this->assertSame(0, $result['totaux']['operations']);
        $this->assertEquals(0, $result['totaux']['progression']);
    }

    /**
     * T-703 : Test dashboard global avec campagnes actives.
     */
    public function testGetDashboardGlobalWithCampagnes(): void
    {
        $campagne1 = $this->createCampagne('Migration W11');
        $this->setEntityId($campagne1, 1);
        $this->setCampagneStatut($campagne1, Campagne::STATUT_EN_COURS);

        $campagne2 = $this->createCampagne('Deploiement O365');
        $this->setEntityId($campagne2, 2);
        $this->setCampagneStatut($campagne2, Campagne::STATUT_A_VENIR);

        $this->campagneRepository
            ->method('findBy')
            ->willReturn([$campagne1, $campagne2]);

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->willReturn([
                Operation::STATUT_A_PLANIFIER => 10,
                Operation::STATUT_PLANIFIE => 20,
                Operation::STATUT_EN_COURS => 0,
                Operation::STATUT_REALISE => 60,
                Operation::STATUT_REPORTE => 5,
                Operation::STATUT_A_REMEDIER => 5,
            ]);

        $this->segmentRepository
            ->method('findByCampagne')
            ->willReturn([]);

        $this->mockEntityManagerWithScalarResult(0);

        $result = $this->service->getDashboardGlobal();

        $this->assertCount(2, $result['campagnes']);
        $this->assertSame(2, $result['totaux']['campagnes']);
        // Total = 100 * 2 campagnes = 200
        $this->assertSame(200, $result['totaux']['operations']);
        // Realise = 60 * 2 = 120
        $this->assertSame(120, $result['totaux']['realise']);
        $this->assertEquals(60.0, $result['totaux']['progression']);
    }

    /**
     * Test calcul pourcentage correct.
     */
    public function testPercentageCalculation(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->willReturn([
                Operation::STATUT_A_PLANIFIER => 1,
                Operation::STATUT_PLANIFIE => 1,
                Operation::STATUT_EN_COURS => 0,
                Operation::STATUT_REALISE => 1,
                Operation::STATUT_REPORTE => 0,
                Operation::STATUT_A_REMEDIER => 0,
            ]);

        $this->mockEntityManagerWithScalarResult(0);

        $kpi = $this->service->getKpiCampagne($campagne);

        // Total = 3, Realise = 1, donc 33.3%
        $this->assertSame(3, $kpi['total']);
        $this->assertSame(1, $kpi['realise']['count']);
        $this->assertSame(33.3, $kpi['realise']['percentage']);
    }

    /**
     * Test couleurs des widgets.
     */
    public function testKpiColors(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->willReturn([
                Operation::STATUT_A_PLANIFIER => 0,
                Operation::STATUT_PLANIFIE => 0,
                Operation::STATUT_EN_COURS => 0,
                Operation::STATUT_REALISE => 0,
                Operation::STATUT_REPORTE => 0,
                Operation::STATUT_A_REMEDIER => 0,
            ]);

        $this->mockEntityManagerWithScalarResult(0);

        $kpi = $this->service->getKpiCampagne($campagne);

        // Verifier les couleurs assignees
        $this->assertSame('success', $kpi['realise']['color']);
        $this->assertSame('primary', $kpi['planifie']['color']);
        $this->assertSame('warning', $kpi['reporte']['color']);
        $this->assertSame('danger', $kpi['a_remedier']['color']);
        $this->assertSame('primary', $kpi['en_cours']['color']);
        $this->assertSame('muted', $kpi['a_planifier']['color']);
    }

    /**
     * Test icones des widgets.
     */
    public function testKpiIcons(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->willReturn([
                Operation::STATUT_A_PLANIFIER => 0,
                Operation::STATUT_PLANIFIE => 0,
                Operation::STATUT_EN_COURS => 0,
                Operation::STATUT_REALISE => 0,
                Operation::STATUT_REPORTE => 0,
                Operation::STATUT_A_REMEDIER => 0,
            ]);

        $this->mockEntityManagerWithScalarResult(0);

        $kpi = $this->service->getKpiCampagne($campagne);

        // Verifier les icones feather assignees
        $this->assertSame('check-circle', $kpi['realise']['icon']);
        $this->assertSame('clock', $kpi['planifie']['icon']);
        $this->assertSame('pause-circle', $kpi['reporte']['icon']);
        $this->assertSame('alert-triangle', $kpi['a_remedier']['icon']);
        $this->assertSame('play-circle', $kpi['en_cours']['icon']);
        $this->assertSame('calendar', $kpi['a_planifier']['icon']);
    }

    /**
     * Test labels des widgets en francais.
     */
    public function testKpiLabels(): void
    {
        $campagne = $this->createCampagne('Test');
        $this->setEntityId($campagne, 1);

        $this->operationRepository
            ->method('countByStatutForCampagne')
            ->willReturn([
                Operation::STATUT_A_PLANIFIER => 0,
                Operation::STATUT_PLANIFIE => 0,
                Operation::STATUT_EN_COURS => 0,
                Operation::STATUT_REALISE => 0,
                Operation::STATUT_REPORTE => 0,
                Operation::STATUT_A_REMEDIER => 0,
            ]);

        $this->mockEntityManagerWithScalarResult(0);

        $kpi = $this->service->getKpiCampagne($campagne);

        // Verifier les labels en francais
        $this->assertSame('Réalisé', $kpi['realise']['label']);
        $this->assertSame('Planifié', $kpi['planifie']['label']);
        $this->assertSame('Reporté', $kpi['reporte']['label']);
        $this->assertSame('À remédier', $kpi['a_remedier']['label']);
        $this->assertSame('En cours', $kpi['en_cours']['label']);
        $this->assertSame('À planifier', $kpi['a_planifier']['label']);
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

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    private function setCampagneStatut(Campagne $campagne, string $statut): void
    {
        $reflection = new \ReflectionClass($campagne);
        $property = $reflection->getProperty('statut');
        $property->setValue($campagne, $statut);
    }

    private function mockEntityManagerWithScalarResult(int $result): void
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSingleScalarResult'])
            ->getMock();
        $query->method('getSingleScalarResult')->willReturn($result);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'where', 'andWhere', 'setParameter', 'getQuery'])
            ->getMock();
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);
    }

    private function mockEntityManagerWithArrayResult(array $results): void
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $query->method('getResult')->willReturn($results);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'where', 'andWhere', 'setParameter', 'groupBy', 'getQuery'])
            ->getMock();
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($qb);
    }
}
