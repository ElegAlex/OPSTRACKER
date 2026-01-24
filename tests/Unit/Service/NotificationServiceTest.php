<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Notification;
use App\Entity\Reservation;
use App\Repository\NotificationRepository;
use App\Repository\ReservationRepository;
use App\Service\IcsGenerator;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Tests unitaires pour NotificationService.
 *
 * Regles metier testees :
 * - RG-140 : Email confirmation contient ICS obligatoire
 * - RG-141 : Email rappel automatique J-X
 * - RG-142 : Email modification = ancien + nouveau + ICS
 * - RG-143 : Email annulation = lien repositionnement
 * - RG-144 : Invitation selon mode (agent/manager)
 */
class NotificationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private NotificationRepository&MockObject $notificationRepository;
    private ReservationRepository&MockObject $reservationRepository;
    private IcsGenerator&MockObject $icsGenerator;
    private MailerInterface&MockObject $mailer;
    private Environment&MockObject $twig;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private LoggerInterface&MockObject $logger;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->notificationRepository = $this->createMock(NotificationRepository::class);
        $this->reservationRepository = $this->createMock(ReservationRepository::class);
        $this->icsGenerator = $this->createMock(IcsGenerator::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new NotificationService(
            $this->entityManager,
            $this->notificationRepository,
            $this->reservationRepository,
            $this->icsGenerator,
            $this->mailer,
            $this->twig,
            $this->urlGenerator,
            $this->logger,
            'opstracker@test.com',
        );
    }

    // ==========================================
    // Tests RG-140 : Email confirmation + ICS
    // ==========================================

    public function testEnvoyerConfirmationCreatesNotification(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->twig
            ->method('render')
            ->willReturn('<html>Email content</html>');

        $this->icsGenerator
            ->method('generate')
            ->with($reservation)
            ->willReturn('BEGIN:VCALENDAR...');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Notification::class));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $this->mailer
            ->expects($this->once())
            ->method('send');

        $notification = $this->service->envoyerConfirmation($reservation);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertSame($agent, $notification->getAgent());
        $this->assertEquals(Notification::TYPE_CONFIRMATION, $notification->getType());
        $this->assertStringContainsString('confirme', $notification->getSujet());
    }

    public function testEnvoyerConfirmationIncludesIcs(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->twig->method('render')->willReturn('<html>Email</html>');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // RG-140 : ICS doit etre genere
        $this->icsGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($reservation)
            ->willReturn('BEGIN:VCALENDAR...');

        $this->mailer->method('send');

        $this->service->envoyerConfirmation($reservation);
    }

    public function testEnvoyerConfirmationMarksAsSentOnSuccess(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->twig->method('render')->willReturn('<html>Email</html>');
        $this->icsGenerator->method('generate')->willReturn('BEGIN:VCALENDAR...');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mailer->method('send');

        $notification = $this->service->envoyerConfirmation($reservation);

        $this->assertEquals(Notification::STATUT_SENT, $notification->getStatut());
    }

    public function testEnvoyerConfirmationMarksAsFailedOnError(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->twig->method('render')->willReturn('<html>Email</html>');
        $this->icsGenerator->method('generate')->willReturn('BEGIN:VCALENDAR...');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $this->mailer
            ->method('send')
            ->willThrowException(new \Exception('SMTP error'));

        $notification = $this->service->envoyerConfirmation($reservation);

        $this->assertEquals(Notification::STATUT_FAILED, $notification->getStatut());
        $this->assertStringContainsString('SMTP error', $notification->getErrorMessage());
    }

    // ==========================================
    // Tests RG-141 : Email rappel J-X
    // ==========================================

    public function testEnvoyerRappelCreatesNotification(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->notificationRepository
            ->method('hasRappelEnvoye')
            ->with($reservation)
            ->willReturn(false);

        $this->twig->method('render')->willReturn('<html>Rappel</html>');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mailer->method('send');

        $notification = $this->service->envoyerRappel($reservation);

        $this->assertEquals(Notification::TYPE_RAPPEL, $notification->getType());
        $this->assertStringContainsString('Rappel', $notification->getSujet());
    }

    public function testEnvoyerRappelThrowsExceptionIfAlreadySent(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->notificationRepository
            ->method('hasRappelEnvoye')
            ->with($reservation)
            ->willReturn(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('rappel a deja ete envoye');

        $this->service->envoyerRappel($reservation);
    }

    public function testEnvoyerRappelsJourReturnsCount(): void
    {
        $agent1 = $this->createAgent();
        $agent1->setMatricule('AGT001');
        $agent2 = $this->createAgent();
        $agent2->setMatricule('AGT002');

        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation1 = $this->createReservation($agent1, $creneau);
        $reservation2 = $this->createReservation($agent2, $creneau);

        $this->reservationRepository
            ->method('findPourRappel')
            ->willReturn([$reservation1, $reservation2]);

        $this->notificationRepository
            ->method('hasRappelEnvoye')
            ->willReturn(false);

        $this->twig->method('render')->willReturn('<html>Rappel</html>');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mailer->method('send');

        $count = $this->service->envoyerRappelsJour(2);

        $this->assertEquals(2, $count);
    }

    public function testEnvoyerRappelsJourSkipsAlreadySent(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $this->reservationRepository
            ->method('findPourRappel')
            ->willReturn([$reservation]);

        // Rappel deja envoye
        $this->notificationRepository
            ->method('hasRappelEnvoye')
            ->willReturn(true);

        $count = $this->service->envoyerRappelsJour(2);

        $this->assertEquals(0, $count);
    }

    // ==========================================
    // Tests RG-142 : Email modification
    // ==========================================

    public function testEnvoyerModificationIncludesOldAndNewCreneau(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $ancienCreneau = $this->createCreneau($campagne);
        $ancienCreneau->setDate(new \DateTime('2026-02-01'));
        $nouveauCreneau = $this->createCreneau($campagne);
        $nouveauCreneau->setDate(new \DateTime('2026-02-15'));

        $reservation = $this->createReservation($agent, $nouveauCreneau);

        // RG-142 : Template doit recevoir ancien et nouveau creneau
        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with(
                'emails/modification.html.twig',
                $this->callback(function ($params) use ($ancienCreneau, $nouveauCreneau) {
                    return $params['ancien_creneau'] === $ancienCreneau
                        && $params['nouveau_creneau'] === $nouveauCreneau;
                })
            )
            ->willReturn('<html>Modification</html>');

        $this->icsGenerator->method('generate')->willReturn('BEGIN:VCALENDAR...');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mailer->method('send');

        $notification = $this->service->envoyerModification($reservation, $ancienCreneau);

        $this->assertEquals(Notification::TYPE_MODIFICATION, $notification->getType());
    }

    public function testEnvoyerModificationIncludesNewIcs(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $ancienCreneau = $this->createCreneau($campagne);
        $nouveauCreneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $nouveauCreneau);

        $this->twig->method('render')->willReturn('<html>Modification</html>');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mailer->method('send');

        // RG-142 : ICS du nouveau creneau
        $this->icsGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($reservation);

        $this->service->envoyerModification($reservation, $ancienCreneau);
    }

    // ==========================================
    // Tests RG-143 : Email annulation
    // ==========================================

    public function testEnvoyerAnnulationIncludesLienRepositionnement(): void
    {
        $agent = $this->createAgent();
        $agent->setBookingToken('abc123');
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        // RG-143 : Lien de repositionnement
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_booking_index',
                ['token' => 'abc123'],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('http://opstracker.local/booking/abc123');

        // Template doit recevoir le lien
        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with(
                'emails/annulation.html.twig',
                $this->callback(function ($params) {
                    return isset($params['lien_repositionnement'])
                        && $params['lien_repositionnement'] === 'http://opstracker.local/booking/abc123';
                })
            )
            ->willReturn('<html>Annulation</html>');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mailer->method('send');

        $notification = $this->service->envoyerAnnulation($reservation);

        $this->assertEquals(Notification::TYPE_ANNULATION, $notification->getType());
    }

    // ==========================================
    // Tests RG-144 : Invitation
    // ==========================================

    public function testEnvoyerInvitationCreatesNotification(): void
    {
        $agent = $this->createAgent();
        $agent->setBookingToken('token123');
        $campagne = $this->createCampagne();

        $this->urlGenerator
            ->method('generate')
            ->willReturn('http://opstracker.local/booking/token123');

        $this->twig
            ->method('render')
            ->willReturn('<html>Invitation</html>');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mailer->method('send');

        $notification = $this->service->envoyerInvitation($agent, $campagne);

        $this->assertEquals(Notification::TYPE_INVITATION, $notification->getType());
        $this->assertNull($notification->getReservation()); // Pas de reservation pour invitation
    }

    public function testEnvoyerInvitationIncludesLienReservation(): void
    {
        $agent = $this->createAgent();
        $agent->setBookingToken('token456');
        $campagne = $this->createCampagne();

        // RG-144 : Lien de reservation avec token
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_booking_index',
                ['token' => 'token456'],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('http://opstracker.local/booking/token456');

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with(
                'emails/invitation.html.twig',
                $this->callback(function ($params) {
                    return isset($params['lien_reservation'])
                        && $params['lien_reservation'] === 'http://opstracker.local/booking/token456';
                })
            )
            ->willReturn('<html>Invitation</html>');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mailer->method('send');

        $this->service->envoyerInvitation($agent, $campagne);
    }

    // ==========================================
    // Tests statut notification
    // ==========================================

    public function testNotificationStatutIsPendingBeforeSend(): void
    {
        $agent = $this->createAgent();
        $campagne = $this->createCampagne();
        $creneau = $this->createCreneau($campagne);
        $reservation = $this->createReservation($agent, $creneau);

        $capturedNotification = null;

        $this->twig->method('render')->willReturn('<html>Email</html>');
        $this->icsGenerator->method('generate')->willReturn('BEGIN:VCALENDAR...');

        $this->entityManager
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                if ($notification instanceof Notification) {
                    $capturedNotification = $notification;
                    // Verifier que le statut est pending AVANT l'envoi
                    $this->assertEquals(Notification::STATUT_PENDING, $notification->getStatut());
                    return true;
                }
                return false;
            }));

        $this->entityManager->method('flush');
        $this->mailer->method('send');

        $this->service->envoyerConfirmation($reservation);
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
        $agent->setBookingToken('default-token');

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
        $creneau->setDate(new \DateTime('2026-02-15'));
        $creneau->setHeureDebut(new \DateTime('09:00'));
        $creneau->setHeureFin(new \DateTime('09:30'));
        $creneau->setCapacite(5);
        $creneau->setLieu('Salle A101');

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
}
