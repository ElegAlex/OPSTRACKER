<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Entity\Segment;
use App\Repository\CreneauRepository;
use App\Service\CreneauService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CreneauService.
 *
 * Regles metier testees :
 * - RG-123 : Verrouillage J-X (defaut: 2 jours avant)
 * - RG-130 : Creation creneaux manuelle ou generation automatique
 * - RG-133 : Modification creneau = notification agents (teste dans Controller)
 * - RG-134 : Suppression creneau = confirmation si reservations (teste dans Controller)
 * - RG-135 : Association creneau <-> segment optionnelle
 */
class CreneauServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CreneauRepository&MockObject $creneauRepository;
    private CreneauService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->creneauRepository = $this->createMock(CreneauRepository::class);

        $this->service = new CreneauService(
            $this->entityManager,
            $this->creneauRepository,
        );
    }

    // ==========================================
    // Tests RG-130 : Creation manuelle
    // ==========================================

    public function testCreerCreneauSuccess(): void
    {
        $campagne = $this->createCampagne();
        $data = [
            'date' => new \DateTime('2026-02-01'),
            'heureDebut' => new \DateTime('09:00'),
            'heureFin' => new \DateTime('09:30'),
            'capacite' => 2,
            'lieu' => 'Salle A',
        ];

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Creneau::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $creneau = $this->service->creer($campagne, $data);

        $this->assertInstanceOf(Creneau::class, $creneau);
        $this->assertSame($campagne, $creneau->getCampagne());
        $this->assertEquals($data['date'], $creneau->getDate());
        $this->assertEquals($data['heureDebut'], $creneau->getHeureDebut());
        $this->assertEquals($data['heureFin'], $creneau->getHeureFin());
        $this->assertEquals(2, $creneau->getCapacite());
        $this->assertEquals('Salle A', $creneau->getLieu());
    }

    public function testCreerCreneauWithDefaultValues(): void
    {
        $campagne = $this->createCampagne();
        $data = [
            'date' => new \DateTime('2026-02-01'),
            'heureDebut' => new \DateTime('09:00'),
            'heureFin' => new \DateTime('09:30'),
        ];

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $creneau = $this->service->creer($campagne, $data);

        $this->assertEquals(1, $creneau->getCapacite()); // Defaut
        $this->assertNull($creneau->getLieu());
        $this->assertNull($creneau->getSegment());
        $this->assertFalse($creneau->isVerrouille());
    }

    public function testCreerCreneauAvecSegment(): void
    {
        $campagne = $this->createCampagne();
        $segment = $this->createSegment($campagne, 'Segment A');
        $data = [
            'date' => new \DateTime('2026-02-01'),
            'heureDebut' => new \DateTime('09:00'),
            'heureFin' => new \DateTime('09:30'),
            'segment' => $segment,
        ];

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $creneau = $this->service->creer($campagne, $data);

        $this->assertSame($segment, $creneau->getSegment());
    }

    // ==========================================
    // Tests RG-130 : Generation automatique
    // ==========================================

    public function testGenererPlageCreatesCorrectNumberOfCreneaux(): void
    {
        $campagne = $this->createCampagne();
        $dateDebut = new \DateTime('2026-02-02'); // Lundi
        $dateFin = new \DateTime('2026-02-02'); // 1 jour
        $dureeMinutes = 30;
        $capacite = 1;

        // Horaires 9h-12h et 14h-17h = 6 creneaux matin + 6 creneaux apres-midi = 12
        $this->entityManager
            ->expects($this->exactly(12))
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $creneaux = $this->service->genererPlage(
            $campagne,
            $dateDebut,
            $dateFin,
            $dureeMinutes,
            $capacite
        );

        $this->assertCount(12, $creneaux);
    }

    public function testGenererPlageSkipsWeekends(): void
    {
        $campagne = $this->createCampagne();
        // Du vendredi au lundi (skip samedi + dimanche)
        $dateDebut = new \DateTime('2026-02-06'); // Vendredi
        $dateFin = new \DateTime('2026-02-09'); // Lundi

        // 2 jours ouvres * 12 creneaux = 24
        $this->entityManager
            ->expects($this->exactly(24))
            ->method('persist');

        $creneaux = $this->service->genererPlage(
            $campagne,
            $dateDebut,
            $dateFin,
            30,
            1
        );

        $this->assertCount(24, $creneaux);

        // Verifier que samedi et dimanche ne sont pas inclus
        foreach ($creneaux as $creneau) {
            $dayOfWeek = (int) $creneau->getDate()->format('N');
            $this->assertLessThan(6, $dayOfWeek, 'Les weekends doivent etre ignores');
        }
    }

    public function testGenererPlageSkipsPauseDejeuner(): void
    {
        $campagne = $this->createCampagne();
        $dateDebut = new \DateTime('2026-02-02'); // Lundi
        $dateFin = new \DateTime('2026-02-02');

        $creneaux = $this->service->genererPlage(
            $campagne,
            $dateDebut,
            $dateFin,
            30,
            1
        );

        // Verifier qu'aucun creneau n'est entre 12h et 14h
        foreach ($creneaux as $creneau) {
            $heureDebut = (int) $creneau->getHeureDebut()->format('H');
            $heureFin = (int) $creneau->getHeureFin()->format('H');

            // Aucun creneau ne doit commencer a 12h ou 13h
            $this->assertNotEquals(12, $heureDebut, 'Pause dejeuner non respectee');
            $this->assertNotEquals(13, $heureDebut, 'Pause dejeuner non respectee');
        }
    }

    public function testGenererPlageWithCustomPlages(): void
    {
        $campagne = $this->createCampagne();
        $dateDebut = new \DateTime('2026-02-02');
        $dateFin = new \DateTime('2026-02-02');

        // Plage unique de 10h a 11h = 2 creneaux de 30 min
        $plagesHoraires = [
            ['debut' => '10:00', 'fin' => '11:00'],
        ];

        $creneaux = $this->service->genererPlage(
            $campagne,
            $dateDebut,
            $dateFin,
            30,
            1,
            null,
            null,
            $plagesHoraires
        );

        $this->assertCount(2, $creneaux);
    }

    public function testGenererPlageWithSegment(): void
    {
        $campagne = $this->createCampagne();
        $segment = $this->createSegment($campagne, 'Segment Test');
        $dateDebut = new \DateTime('2026-02-02');
        $dateFin = new \DateTime('2026-02-02');

        $creneaux = $this->service->genererPlage(
            $campagne,
            $dateDebut,
            $dateFin,
            60,
            2,
            'Bureau 101',
            $segment,
            [['debut' => '09:00', 'fin' => '10:00']]
        );

        $this->assertCount(1, $creneaux);
        $this->assertSame($segment, $creneaux[0]->getSegment());
        $this->assertEquals('Bureau 101', $creneaux[0]->getLieu());
        $this->assertEquals(2, $creneaux[0]->getCapacite());
    }

    // ==========================================
    // Tests Modification
    // ==========================================

    public function testModifierCreneauSuccess(): void
    {
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $newDate = new \DateTime('2026-03-01');
        $result = $this->service->modifier($creneau, [
            'date' => $newDate,
            'capacite' => 5,
        ]);

        $this->assertEquals($newDate, $result->getDate());
        $this->assertEquals(5, $result->getCapacite());
    }

    // ==========================================
    // Tests Suppression
    // ==========================================

    public function testSupprimerCreneauSuccess(): void
    {
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($creneau);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->supprimer($creneau);
    }

    // ==========================================
    // Tests RG-123 : Verrouillage automatique
    // ==========================================

    public function testVerrouillerAutomatiqueSuccess(): void
    {
        $campagne = $this->createCampagne();
        $creneau1 = $this->createCreneau($campagne);
        $creneau2 = $this->createCreneau($campagne);

        $this->creneauRepository
            ->method('findAVerrouiller')
            ->with(2)
            ->willReturn([$creneau1, $creneau2]);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $count = $this->service->verrouillerAutomatique(2);

        $this->assertEquals(2, $count);
        $this->assertTrue($creneau1->isVerrouille());
        $this->assertTrue($creneau2->isVerrouille());
    }

    // ==========================================
    // Tests Helpers
    // ==========================================

    public function testHasReservationsReturnsTrueWhenReservationsExist(): void
    {
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);

        $reservation = $this->createMock(Reservation::class);
        $creneau->addReservation($reservation);

        $this->assertTrue($this->service->hasReservations($creneau));
    }

    public function testHasReservationsReturnsFalseWhenEmpty(): void
    {
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);

        $this->assertFalse($this->service->hasReservations($creneau));
    }

    public function testCountReservationsConfirmees(): void
    {
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);

        $reservation1 = $this->createMock(Reservation::class);
        $reservation1->method('getStatut')->willReturn('confirmee');

        $reservation2 = $this->createMock(Reservation::class);
        $reservation2->method('getStatut')->willReturn('annulee');

        $creneau->addReservation($reservation1);
        $creneau->addReservation($reservation2);

        $count = $this->service->countReservationsConfirmees($creneau);

        $this->assertEquals(1, $count);
    }

    public function testGetDisponiblesCallsRepository(): void
    {
        $campagne = $this->createCampagne();
        $segment = $this->createSegment($campagne, 'Segment');
        $expectedCreneaux = [$this->createCreneau($campagne)];

        $this->creneauRepository
            ->expects($this->once())
            ->method('findDisponibles')
            ->with($campagne, $segment)
            ->willReturn($expectedCreneaux);

        $result = $this->service->getDisponibles($campagne, $segment);

        $this->assertSame($expectedCreneaux, $result);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createCampagne(): Campagne
    {
        $campagne = new Campagne();
        $campagne->setNom('Campagne Test');
        $campagne->setDateDebut(new \DateTime('2026-02-01'));
        $campagne->setDateFin(new \DateTime('2026-03-31'));

        return $campagne;
    }

    private function createSegment(Campagne $campagne, string $nom): Segment
    {
        $segment = new Segment();
        $segment->setNom($nom);
        $segment->setCampagne($campagne);

        return $segment;
    }

    private function createCreneau(Campagne $campagne): Creneau
    {
        $creneau = new Creneau();
        $creneau->setCampagne($campagne);
        $creneau->setDate(new \DateTime('2026-02-15'));
        $creneau->setHeureDebut(new \DateTime('09:00'));
        $creneau->setHeureFin(new \DateTime('09:30'));
        $creneau->setCapacite(1);

        return $creneau;
    }
}
