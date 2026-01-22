<?php

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Entity\Prerequis;
use App\Entity\Segment;
use App\Repository\PrerequisRepository;
use App\Service\PrerequisService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour PrerequisService.
 *
 * Regles metier testees :
 * - RG-090 : Prerequis globaux de campagne
 * - RG-091 : Prerequis specifiques a un segment
 */
class PrerequisServiceTest extends TestCase
{
    private PrerequisService $service;
    private EntityManagerInterface $em;
    private PrerequisRepository $repository;
    private Campagne $campagne;
    private Segment $segment;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(PrerequisRepository::class);

        $this->service = new PrerequisService($this->em, $this->repository);

        // Creer une campagne de test
        $this->campagne = new Campagne();
        $this->campagne->setNom('Campagne Test');
        $this->campagne->setDateDebut(new \DateTimeImmutable('2026-01-01'));
        $this->campagne->setDateFin(new \DateTimeImmutable('2026-12-31'));

        // Creer un segment de test
        $this->segment = new Segment();
        $this->segment->setNom('Segment Test');
        $this->segment->setCampagne($this->campagne);
    }

    /**
     * RG-090 : Test recuperation prerequis globaux avec progression
     */
    public function testGetPrerequisGlobaux(): void
    {
        $prerequis1 = new Prerequis();
        $prerequis1->setLibelle('Prerequis 1');
        $prerequis1->setCampagne($this->campagne);
        $prerequis1->setStatut(Prerequis::STATUT_FAIT);

        $prerequis2 = new Prerequis();
        $prerequis2->setLibelle('Prerequis 2');
        $prerequis2->setCampagne($this->campagne);
        $prerequis2->setStatut(Prerequis::STATUT_A_FAIRE);

        $this->repository->expects($this->once())
            ->method('findGlobauxByCampagne')
            ->with($this->campagne)
            ->willReturn([$prerequis1, $prerequis2]);

        $this->repository->expects($this->once())
            ->method('getProgressionGlobale')
            ->with($this->campagne)
            ->willReturn(['total' => 2, 'faits' => 1, 'pourcentage' => 50]);

        $result = $this->service->getPrerequisGlobaux($this->campagne);

        $this->assertArrayHasKey('prerequis', $result);
        $this->assertArrayHasKey('progression', $result);
        $this->assertCount(2, $result['prerequis']);
        $this->assertEquals(50, $result['progression']['pourcentage']);
    }

    /**
     * RG-090 : Test creation prerequis global
     */
    public function testCreerPrerequisGlobal(): void
    {
        $this->repository->expects($this->once())
            ->method('getNextOrdre')
            ->with($this->campagne)
            ->willReturn(1);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Prerequis $p) {
                return $p->getLibelle() === 'Test Prerequis'
                    && $p->getResponsable() === 'Jean Dupont'
                    && $p->getCampagne() === $this->campagne
                    && $p->getSegment() === null;
            }));

        $this->em->expects($this->once())
            ->method('flush');

        $dateCible = new \DateTimeImmutable('2026-06-01');
        $result = $this->service->creerPrerequisGlobal(
            $this->campagne,
            'Test Prerequis',
            'Jean Dupont',
            $dateCible
        );

        $this->assertInstanceOf(Prerequis::class, $result);
        $this->assertEquals('Test Prerequis', $result->getLibelle());
        $this->assertTrue($result->isGlobal());
    }

    /**
     * RG-091 : Test creation prerequis segment
     */
    public function testCreerPrerequisSegment(): void
    {
        $this->repository->expects($this->once())
            ->method('getNextOrdre')
            ->with($this->campagne, $this->segment)
            ->willReturn(1);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Prerequis $p) {
                return $p->getLibelle() === 'Test Prerequis Segment'
                    && $p->getSegment() === $this->segment
                    && $p->getCampagne() === $this->campagne;
            }));

        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->service->creerPrerequisSegment(
            $this->segment,
            'Test Prerequis Segment',
            'Marie Martin'
        );

        $this->assertInstanceOf(Prerequis::class, $result);
        $this->assertFalse($result->isGlobal());
        $this->assertEquals($this->segment, $result->getSegment());
    }

    /**
     * Test mise a jour du statut
     */
    public function testUpdateStatut(): void
    {
        $prerequis = new Prerequis();
        $prerequis->setLibelle('Test');
        $prerequis->setCampagne($this->campagne);
        $prerequis->setStatut(Prerequis::STATUT_A_FAIRE);

        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->service->updateStatut($prerequis, Prerequis::STATUT_FAIT);

        $this->assertEquals(Prerequis::STATUT_FAIT, $result->getStatut());
    }

    /**
     * Test mise a jour d'un prerequis
     */
    public function testUpdate(): void
    {
        $prerequis = new Prerequis();
        $prerequis->setLibelle('Ancien libelle');
        $prerequis->setCampagne($this->campagne);

        $this->em->expects($this->once())
            ->method('flush');

        $dateCible = new \DateTimeImmutable('2026-03-15');
        $result = $this->service->update(
            $prerequis,
            'Nouveau libelle',
            'Nouveau responsable',
            $dateCible
        );

        $this->assertEquals('Nouveau libelle', $result->getLibelle());
        $this->assertEquals('Nouveau responsable', $result->getResponsable());
        $this->assertEquals($dateCible, $result->getDateCible());
    }

    /**
     * Test suppression prerequis
     */
    public function testSupprimer(): void
    {
        $prerequis = new Prerequis();
        $prerequis->setLibelle('A supprimer');
        $prerequis->setCampagne($this->campagne);

        $this->em->expects($this->once())
            ->method('remove')
            ->with($prerequis);

        $this->em->expects($this->once())
            ->method('flush');

        $this->service->supprimer($prerequis);
    }

    /**
     * Test des statuts de prerequis
     */
    public function testStatutsConstantes(): void
    {
        $this->assertEquals('a_faire', Prerequis::STATUT_A_FAIRE);
        $this->assertEquals('en_cours', Prerequis::STATUT_EN_COURS);
        $this->assertEquals('fait', Prerequis::STATUT_FAIT);

        $this->assertCount(3, Prerequis::STATUTS);
    }

    /**
     * Test couleurs et icones des statuts
     */
    public function testStatutCouleurEtIcone(): void
    {
        $prerequis = new Prerequis();
        $prerequis->setLibelle('Test');
        $prerequis->setCampagne($this->campagne);

        $prerequis->setStatut(Prerequis::STATUT_A_FAIRE);
        $this->assertEquals('muted', $prerequis->getStatutCouleur());
        $this->assertEquals('circle', $prerequis->getStatutIcone());

        $prerequis->setStatut(Prerequis::STATUT_EN_COURS);
        $this->assertEquals('primary', $prerequis->getStatutCouleur());
        $this->assertEquals('clock', $prerequis->getStatutIcone());

        $prerequis->setStatut(Prerequis::STATUT_FAIT);
        $this->assertEquals('success', $prerequis->getStatutCouleur());
        $this->assertEquals('check-circle', $prerequis->getStatutIcone());
    }

    /**
     * Test detection retard
     */
    public function testIsEnRetard(): void
    {
        $prerequis = new Prerequis();
        $prerequis->setLibelle('Test');
        $prerequis->setCampagne($this->campagne);

        // Sans date cible, pas en retard
        $this->assertFalse($prerequis->isEnRetard());

        // Date future, pas en retard
        $prerequis->setDateCible(new \DateTimeImmutable('+1 week'));
        $this->assertFalse($prerequis->isEnRetard());

        // Date passee et pas fait, en retard
        $prerequis->setDateCible(new \DateTimeImmutable('-1 week'));
        $this->assertTrue($prerequis->isEnRetard());

        // Date passee mais fait, pas en retard
        $prerequis->setStatut(Prerequis::STATUT_FAIT);
        $this->assertFalse($prerequis->isEnRetard());
    }

    /**
     * Test exception statut invalide
     */
    public function testStatutInvalideThrowsException(): void
    {
        $prerequis = new Prerequis();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statut invalide');

        $prerequis->setStatut('invalide');
    }

    /**
     * Test isGlobal selon presence segment
     */
    public function testIsGlobal(): void
    {
        $prerequis = new Prerequis();
        $prerequis->setLibelle('Test');
        $prerequis->setCampagne($this->campagne);

        // Sans segment = global
        $this->assertTrue($prerequis->isGlobal());

        // Avec segment = pas global
        $prerequis->setSegment($this->segment);
        $this->assertFalse($prerequis->isGlobal());
    }
}
