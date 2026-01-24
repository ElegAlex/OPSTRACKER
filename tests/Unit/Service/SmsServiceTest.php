<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Notification;
use App\Entity\Reservation;
use App\Service\Sms\LogSmsProvider;
use App\Service\Sms\SmsProviderInterface;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires pour SmsService (Sprint V2.1c).
 *
 * Scenarios testes :
 * - Agent avec opt-in et telephone valide -> SMS envoye
 * - Agent sans opt-in -> SMS non envoye
 * - Agent avec opt-in sans telephone -> SMS non envoye
 * - SMS desactive globalement -> SMS non envoye
 * - Test des differents types de SMS (rappel, confirmation, annulation)
 */
class SmsServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private SmsProviderInterface&MockObject $provider;
    private SmsService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->provider = $this->createMock(SmsProviderInterface::class);

        $this->service = new SmsService(
            $this->provider,
            $this->entityManager,
            new NullLogger(),
            true // smsEnabled
        );
    }

    // ==========================================
    // Tests envoyerRappel
    // ==========================================

    public function testEnvoyerRappelAgentOptInAvecTelephone(): void
    {
        $agent = $this->createAgent(true, '+33612345678');
        $reservation = $this->createReservation($agent);

        $this->provider
            ->expects($this->once())
            ->method('send')
            ->with('+33612345678', $this->stringContains('Rappel'))
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Notification::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->envoyerRappel($reservation);

        $this->assertTrue($result);
    }

    public function testEnvoyerRappelAgentSansOptIn(): void
    {
        $agent = $this->createAgent(false, '+33612345678');
        $reservation = $this->createReservation($agent);

        $this->provider
            ->expects($this->never())
            ->method('send');

        $result = $this->service->envoyerRappel($reservation);

        $this->assertFalse($result);
    }

    public function testEnvoyerRappelAgentSansTelephone(): void
    {
        $agent = $this->createAgent(true, null);
        $reservation = $this->createReservation($agent);

        $this->provider
            ->expects($this->never())
            ->method('send');

        $result = $this->service->envoyerRappel($reservation);

        $this->assertFalse($result);
    }

    public function testEnvoyerRappelSmsDisabled(): void
    {
        // Creer un service avec SMS desactive
        $service = new SmsService(
            $this->provider,
            $this->entityManager,
            new NullLogger(),
            false // smsEnabled = false
        );

        $agent = $this->createAgent(true, '+33612345678');
        $reservation = $this->createReservation($agent);

        $this->provider
            ->expects($this->never())
            ->method('send');

        $result = $service->envoyerRappel($reservation);

        $this->assertFalse($result);
    }

    // ==========================================
    // Tests envoyerConfirmation
    // ==========================================

    public function testEnvoyerConfirmationAgentOptIn(): void
    {
        $agent = $this->createAgent(true, '+33612345678');
        $reservation = $this->createReservation($agent);

        $this->provider
            ->expects($this->once())
            ->method('send')
            ->with('+33612345678', $this->stringContains('Confirme'))
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->envoyerConfirmation($reservation);

        $this->assertTrue($result);
    }

    public function testEnvoyerConfirmationAgentSansOptIn(): void
    {
        $agent = $this->createAgent(false, '+33612345678');
        $reservation = $this->createReservation($agent);

        $this->provider
            ->expects($this->never())
            ->method('send');

        $result = $this->service->envoyerConfirmation($reservation);

        $this->assertFalse($result);
    }

    // ==========================================
    // Tests envoyerAnnulation
    // ==========================================

    public function testEnvoyerAnnulationAgentOptIn(): void
    {
        $agent = $this->createAgent(true, '+33612345678');
        $reservation = $this->createReservation($agent);

        $this->provider
            ->expects($this->once())
            ->method('send')
            ->with('+33612345678', $this->stringContains('annule'))
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->envoyerAnnulation($reservation);

        $this->assertTrue($result);
    }

    // ==========================================
    // Tests echec envoi
    // ==========================================

    public function testEnvoyerRappelEchecProvider(): void
    {
        $agent = $this->createAgent(true, '+33612345678');
        $reservation = $this->createReservation($agent);

        $this->provider
            ->expects($this->once())
            ->method('send')
            ->willReturn(false);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Notification $notification): bool {
                // La notification doit etre creee meme en cas d'echec
                return $notification->getType() === 'rappel_sms';
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->envoyerRappel($reservation);

        $this->assertFalse($result);
    }

    // ==========================================
    // Tests isEnabled / getProviderName
    // ==========================================

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->service->isEnabled());

        $disabledService = new SmsService(
            $this->provider,
            $this->entityManager,
            new NullLogger(),
            false
        );

        $this->assertFalse($disabledService->isEnabled());
    }

    public function testGetProviderName(): void
    {
        $this->provider
            ->method('getProviderName')
            ->willReturn('test_provider');

        $this->assertEquals('test_provider', $this->service->getProviderName());
    }

    // ==========================================
    // Tests avec LogSmsProvider
    // ==========================================

    public function testEnvoyerRappelAvecLogProvider(): void
    {
        $logProvider = new LogSmsProvider(new NullLogger());
        $service = new SmsService(
            $logProvider,
            $this->entityManager,
            new NullLogger(),
            true
        );

        $agent = $this->createAgent(true, '+33612345678');
        $reservation = $this->createReservation($agent);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $service->envoyerRappel($reservation);

        $this->assertTrue($result);
        $this->assertEquals('log', $logProvider->getProviderName());
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createAgent(bool $smsOptIn = true, ?string $telephone = '+33612345678'): Agent
    {
        $agent = new Agent();
        $agent->setMatricule('TEST001');
        $agent->setEmail('test@test.com');
        $agent->setNom('Test');
        $agent->setPrenom('Agent');
        $agent->setSmsOptIn($smsOptIn);
        if ($telephone !== null) {
            $agent->setTelephone($telephone);
        }

        return $agent;
    }

    private function createCampagne(): Campagne
    {
        $campagne = new Campagne();
        $campagne->setNom('Campagne Test');
        $campagne->setStatut(Campagne::STATUT_EN_COURS);
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+30 days'));

        return $campagne;
    }

    private function createCreneau(?Campagne $campagne = null): Creneau
    {
        $creneau = new Creneau();
        $creneau->setDate(new \DateTime('+1 day'));
        $creneau->setHeureDebut(new \DateTime('09:00'));
        $creneau->setHeureFin(new \DateTime('09:30'));
        $creneau->setCapacite(2);
        $creneau->setLieu('Salle 1');
        if ($campagne !== null) {
            $creneau->setCampagne($campagne);
        }

        return $creneau;
    }

    private function createReservation(Agent $agent, ?Creneau $creneau = null): Reservation
    {
        $campagne = $this->createCampagne();
        $creneau = $creneau ?? $this->createCreneau($campagne);

        $reservation = new Reservation();
        $reservation->setAgent($agent);
        $reservation->setCreneau($creneau);
        $reservation->setCampagne($campagne);
        $reservation->setStatut(Reservation::STATUT_CONFIRMEE);
        $reservation->setTypePositionnement(Reservation::TYPE_AGENT);

        return $reservation;
    }
}
