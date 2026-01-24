<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests unitaires pour ReservationService.
 *
 * Regles metier testees :
 * - RG-121 : Un agent = un seul creneau par campagne
 * - RG-122 : Confirmation automatique = email + ICS
 * - RG-123 : Verrouillage J-X
 * - RG-125 : Tracabilite : enregistrer qui a positionne
 * - RG-126 : Notification agent si positionne par tiers
 */
class ReservationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ReservationRepository&MockObject $reservationRepository;
    private NotificationService&MockObject $notificationService;
    private LoggerInterface&MockObject $logger;
    private ReservationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->reservationRepository = $this->createMock(ReservationRepository::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ReservationService(
            $this->entityManager,
            $this->reservationRepository,
            $this->notificationService,
            $this->logger,
        );
    }

    // ==========================================
    // Tests RG-121 : Reserver un creneau
    // ==========================================

    public function testReserverSuccess(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->with($agent, $campagne)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Reservation::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->notificationService
            ->expects($this->once())
            ->method('envoyerConfirmation');

        $reservation = $this->service->reserver($agent, $creneau);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertSame($agent, $reservation->getAgent());
        $this->assertSame($creneau, $reservation->getCreneau());
        $this->assertSame($campagne, $reservation->getCampagne());
        $this->assertEquals(Reservation::TYPE_AGENT, $reservation->getTypePositionnement());
        $this->assertEquals(Reservation::STATUT_CONFIRMEE, $reservation->getStatut());
    }

    public function testReserverByManagerWithTracability(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $manager = $this->createUtilisateur();

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->willReturn(null);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->notificationService->method('envoyerConfirmation');

        $reservation = $this->service->reserver(
            $agent,
            $creneau,
            Reservation::TYPE_MANAGER,
            $manager
        );

        // RG-125 : Tracabilite
        $this->assertEquals(Reservation::TYPE_MANAGER, $reservation->getTypePositionnement());
        $this->assertSame($manager, $reservation->getPositionnePar());
    }

    public function testReserverThrowsExceptionWhenAgentAlreadyHasReservation(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $existingReservation = $this->createReservation($agent, $creneau);

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->with($agent, $campagne)
            ->willReturn($existingReservation);

        // RG-121 : Unicite agent/campagne
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('a deja une reservation pour cette campagne');

        $this->service->reserver($agent, $creneau);
    }

    public function testReserverThrowsExceptionWhenCreneauIsComplet(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneauComplet($campagne);

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('creneau est complet');

        $this->service->reserver($agent, $creneau);
    }

    public function testReserverThrowsExceptionWhenCreneauIsVerrouille(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneauVerrouille($campagne);

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('creneau est verrouille');

        $this->service->reserver($agent, $creneau);
    }

    // ==========================================
    // Tests RG-122, RG-126 : Notifications
    // ==========================================

    public function testReserverEnvoieNotificationConfirmation(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->willReturn(null);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // RG-122 : Verification appel notification
        $this->notificationService
            ->expects($this->once())
            ->method('envoyerConfirmation')
            ->with($this->isInstanceOf(Reservation::class));

        $this->service->reserver($agent, $creneau);
    }

    public function testReserverContinuesIfNotificationFails(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->willReturn(null);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $this->notificationService
            ->method('envoyerConfirmation')
            ->willThrowException(new \Exception('Email error'));

        // Should log error
        $this->logger
            ->expects($this->once())
            ->method('error');

        // Should NOT throw exception
        $reservation = $this->service->reserver($agent, $creneau);
        $this->assertInstanceOf(Reservation::class, $reservation);
    }

    // ==========================================
    // Tests Modification
    // ==========================================

    public function testModifierSuccess(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $ancienCreneau = $this->createCreneau($campagne);
        $nouveauCreneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $ancienCreneau);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->notificationService
            ->expects($this->once())
            ->method('envoyerModification')
            ->with($reservation, $ancienCreneau);

        $result = $this->service->modifier($reservation, $nouveauCreneau);

        $this->assertSame($nouveauCreneau, $result->getCreneau());
    }

    public function testModifierThrowsExceptionWhenNewCreneauIsComplet(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $ancienCreneau = $this->createCreneau($campagne);
        $nouveauCreneauComplet = $this->createCreneauComplet($campagne);
        $reservation = $this->createReservation($agent, $ancienCreneau);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('nouveau creneau est complet');

        $this->service->modifier($reservation, $nouveauCreneauComplet);
    }

    public function testModifierThrowsExceptionWhenNewCreneauIsVerrouille(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $ancienCreneau = $this->createCreneau($campagne);
        $nouveauCreneauVerrouille = $this->createCreneauVerrouille($campagne);
        $reservation = $this->createReservation($agent, $ancienCreneau);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('nouveau creneau est verrouille');

        $this->service->modifier($reservation, $nouveauCreneauVerrouille);
    }

    // ==========================================
    // Tests Annulation
    // ==========================================

    public function testAnnulerSuccess(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->notificationService
            ->expects($this->once())
            ->method('envoyerAnnulation')
            ->with($reservation);

        $this->service->annuler($reservation);

        $this->assertEquals(Reservation::STATUT_ANNULEE, $reservation->getStatut());
    }

    // ==========================================
    // Tests getByAgent / getByManager
    // ==========================================

    public function testGetByAgentCallsRepository(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $expectedReservation = $this->createReservation($agent, $creneau);

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByAgentAndCampagne')
            ->with($agent, $campagne)
            ->willReturn($expectedReservation);

        $result = $this->service->getByAgent($agent, $campagne);

        $this->assertSame($expectedReservation, $result);
    }

    public function testGetByAgentReturnsNullWhenNoReservation(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->willReturn(null);

        $result = $this->service->getByAgent($agent, $campagne);

        $this->assertNull($result);
    }

    public function testHasReservationReturnsTrue(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->willReturn($reservation);

        $result = $this->service->hasReservation($agent, $campagne);

        $this->assertTrue($result);
    }

    public function testHasReservationReturnsFalse(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();

        $this->reservationRepository
            ->method('findByAgentAndCampagne')
            ->willReturn(null);

        $result = $this->service->hasReservation($agent, $campagne);

        $this->assertFalse($result);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createAgent(): Agent
    {
        $agent = new Agent();
        $agent->setMatricule('TEST001');
        $agent->setEmail('agent@test.com');
        $agent->setNom('Dupont');
        $agent->setPrenom('Jean');

        return $agent;
    }

    private function createCampagne(): Campagne
    {
        $campagne = new Campagne();
        $campagne->setNom('Campagne Test');
        $campagne->setDateDebut(new \DateTimeImmutable('2026-02-01'));
        $campagne->setDateFin(new \DateTimeImmutable('2026-03-31'));

        return $campagne;
    }

    private function createCreneau(Campagne $campagne): Creneau
    {
        $creneau = new Creneau();
        $creneau->setCampagne($campagne);
        $creneau->setDate(new \DateTime('+30 days')); // Date future (pas verrouille)
        $creneau->setHeureDebut(new \DateTime('09:00'));
        $creneau->setHeureFin(new \DateTime('09:30'));
        $creneau->setCapacite(5);

        return $creneau;
    }

    private function createCreneauComplet(Campagne $campagne): Creneau
    {
        $creneau = new Creneau();
        $creneau->setCampagne($campagne);
        $creneau->setDate(new \DateTime('+30 days'));
        $creneau->setHeureDebut(new \DateTime('09:00'));
        $creneau->setHeureFin(new \DateTime('09:30'));
        $creneau->setCapacite(1);

        // Ajouter une reservation confirmee pour rendre complet
        $agent = $this->createAgent();
        $agent->setMatricule('FULL001');
        $reservation = new Reservation();
        $reservation->setAgent($agent);
        $reservation->setCreneau($creneau);
        $reservation->setCampagne($campagne);
        $creneau->addReservation($reservation);

        return $creneau;
    }

    private function createCreneauVerrouille(Campagne $campagne): Creneau
    {
        $creneau = new Creneau();
        $creneau->setCampagne($campagne);
        $creneau->setDate(new \DateTime('tomorrow')); // Verrouille car J-2
        $creneau->setHeureDebut(new \DateTime('09:00'));
        $creneau->setHeureFin(new \DateTime('09:30'));
        $creneau->setCapacite(5);

        return $creneau;
    }

    private function createReservation(Agent $agent, Creneau $creneau): Reservation
    {
        $reservation = new Reservation();
        $reservation->setAgent($agent);
        $reservation->setCreneau($creneau);
        $reservation->setCampagne($creneau->getCampagne());

        return $reservation;
    }

    private function createUtilisateur(): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail('manager@test.com');
        $utilisateur->setNom('Manager');
        $utilisateur->setPrenom('Test');
        $utilisateur->setPassword('password');

        return $utilisateur;
    }
}
