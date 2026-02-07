<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Notification;
use App\Entity\Reservation;
use App\Repository\NotificationRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Service de gestion des notifications pour OpsTracker V2.
 *
 * Regles metier :
 * - RG-140 : Email confirmation contient ICS obligatoire
 * - RG-141 : Email rappel automatique J-X
 * - RG-142 : Email modification = ancien + nouveau + ICS
 * - RG-143 : Email annulation = lien repositionnement
 * - RG-144 : Invitation selon mode (agent/manager)
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private ReservationRepository $reservationRepository,
        private IcsGenerator $icsGenerator,
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private ?LoggerInterface $logger = null,
        private string $senderEmail = 'noreply@demo.opstracker.local',
    ) {
    }

    /**
     * RG-140 : Envoie un email de confirmation avec ICS
     */
    public function envoyerConfirmation(Reservation $reservation): Notification
    {
        $agent = $reservation->getAgent();
        $creneau = $reservation->getCreneau();

        if ($agent === null || $creneau === null) {
            throw new \InvalidArgumentException('Agent et creneau sont obligatoires pour la confirmation.');
        }

        $date = $creneau->getDate();
        if ($date === null) {
            throw new \InvalidArgumentException('Le creneau doit avoir une date.');
        }

        $sujet = sprintf(
            '[OpsTracker] Votre rendez-vous du %s est confirme',
            $date->format('d/m/Y')
        );

        $contenu = $this->twig->render('emails/confirmation.html.twig', [
            'reservation' => $reservation,
            'agent' => $agent,
            'creneau' => $creneau,
        ]);

        $notification = $this->creerNotification(
            $agent,
            Notification::TYPE_CONFIRMATION,
            $sujet,
            $contenu,
            $reservation
        );

        // RG-140 : Envoyer l'email avec ICS obligatoire
        $this->envoyerEmailAvecIcs($notification, $reservation);

        return $notification;
    }

    /**
     * RG-141 : Envoie un email de rappel J-X
     */
    public function envoyerRappel(Reservation $reservation): Notification
    {
        $agent = $reservation->getAgent();
        $creneau = $reservation->getCreneau();

        if ($agent === null || $creneau === null) {
            throw new \InvalidArgumentException('Agent et creneau sont obligatoires pour le rappel.');
        }

        $date = $creneau->getDate();
        if ($date === null) {
            throw new \InvalidArgumentException('Le creneau doit avoir une date.');
        }

        // Verifier si un rappel a deja ete envoye
        if ($this->notificationRepository->hasRappelEnvoye($reservation)) {
            throw new \LogicException('Un rappel a deja ete envoye pour cette reservation.');
        }

        $sujet = sprintf(
            '[OpsTracker] Rappel : votre rendez-vous du %s',
            $date->format('d/m/Y')
        );

        $contenu = $this->twig->render('emails/rappel.html.twig', [
            'reservation' => $reservation,
            'agent' => $agent,
            'creneau' => $creneau,
        ]);

        $notification = $this->creerNotification(
            $agent,
            Notification::TYPE_RAPPEL,
            $sujet,
            $contenu,
            $reservation
        );

        $this->envoyerEmail($notification);

        return $notification;
    }

    /**
     * RG-142 : Envoie un email de modification avec ancien + nouveau creneau + ICS
     */
    public function envoyerModification(Reservation $reservation, Creneau $ancienCreneau): Notification
    {
        $agent = $reservation->getAgent();
        $nouveauCreneau = $reservation->getCreneau();

        if ($agent === null || $nouveauCreneau === null) {
            throw new \InvalidArgumentException('Agent et creneau sont obligatoires pour la modification.');
        }

        $ancienneDate = $ancienCreneau->getDate();
        if ($ancienneDate === null) {
            throw new \InvalidArgumentException('L\'ancien creneau doit avoir une date.');
        }

        $sujet = sprintf(
            '[OpsTracker] Modification de votre rendez-vous du %s',
            $ancienneDate->format('d/m/Y')
        );

        $contenu = $this->twig->render('emails/modification.html.twig', [
            'reservation' => $reservation,
            'agent' => $agent,
            'ancien_creneau' => $ancienCreneau,
            'nouveau_creneau' => $nouveauCreneau,
        ]);

        $notification = $this->creerNotification(
            $agent,
            Notification::TYPE_MODIFICATION,
            $sujet,
            $contenu,
            $reservation
        );

        // RG-142 : Envoyer l'email avec le nouvel ICS
        $this->envoyerEmailAvecIcs($notification, $reservation);

        return $notification;
    }

    /**
     * RG-143 : Envoie un email d'annulation avec lien repositionnement
     */
    public function envoyerAnnulation(Reservation $reservation): Notification
    {
        $agent = $reservation->getAgent();
        $creneau = $reservation->getCreneau();

        if ($agent === null || $creneau === null) {
            throw new \InvalidArgumentException('Agent et creneau sont obligatoires pour l\'annulation.');
        }

        $date = $creneau->getDate();
        if ($date === null) {
            throw new \InvalidArgumentException('Le creneau doit avoir une date.');
        }

        // RG-143 : Generer le lien de repositionnement
        $lienRepositionnement = $this->urlGenerator->generate(
            'app_booking_index',
            ['token' => $agent->getBookingToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $sujet = sprintf(
            '[OpsTracker] Votre rendez-vous du %s a ete annule',
            $date->format('d/m/Y')
        );

        $contenu = $this->twig->render('emails/annulation.html.twig', [
            'reservation' => $reservation,
            'agent' => $agent,
            'creneau' => $creneau,
            'lien_repositionnement' => $lienRepositionnement,
        ]);

        $notification = $this->creerNotification(
            $agent,
            Notification::TYPE_ANNULATION,
            $sujet,
            $contenu,
            $reservation
        );

        $this->envoyerEmail($notification);

        return $notification;
    }

    /**
     * RG-144 : Envoie une invitation initiale a un agent
     */
    public function envoyerInvitation(Agent $agent, Campagne $campagne): Notification
    {
        // Generer le lien de reservation avec le token de l'agent
        $lienReservation = $this->urlGenerator->generate(
            'app_booking_index',
            ['token' => $agent->getBookingToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $sujet = sprintf(
            '[OpsTracker] Invitation a reserver votre creneau - %s',
            $campagne->getNom()
        );

        $contenu = $this->twig->render('emails/invitation.html.twig', [
            'agent' => $agent,
            'campagne' => $campagne,
            'lien_reservation' => $lienReservation,
        ]);

        $notification = $this->creerNotification(
            $agent,
            Notification::TYPE_INVITATION,
            $sujet,
            $contenu
        );

        $this->envoyerEmail($notification);

        return $notification;
    }

    /**
     * RG-141 : Envoie les rappels pour les reservations du jour J+X
     *
     * @return int Nombre de rappels envoyes
     */
    public function envoyerRappelsJour(int $joursAvant = 2): int
    {
        $dateRappel = (new \DateTime())->modify("+{$joursAvant} days");
        $reservations = $this->reservationRepository->findPourRappel($dateRappel);

        $count = 0;
        foreach ($reservations as $reservation) {
            try {
                $this->envoyerRappel($reservation);
                $count++;
            } catch (\LogicException $e) {
                // Rappel deja envoye, on continue
                $this->logger?->info('Rappel deja envoye pour reservation #{id}', [
                    'id' => $reservation->getId(),
                ]);
            } catch (\Exception $e) {
                // Log l'erreur et continue
                $this->logger?->error('Erreur envoi rappel reservation #{id}: {error}', [
                    'id' => $reservation->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Cree une notification en base
     */
    private function creerNotification(
        Agent $agent,
        string $type,
        string $sujet,
        string $contenu,
        ?Reservation $reservation = null
    ): Notification {
        $notification = new Notification();
        $notification->setAgent($agent);
        $notification->setType($type);
        $notification->setSujet($sujet);
        $notification->setContenu($contenu);
        $notification->setReservation($reservation);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Envoie un email simple
     */
    private function envoyerEmail(Notification $notification): void
    {
        $agent = $notification->getAgent();
        if ($agent === null) {
            $notification->markAsFailed('Agent non defini');
            $this->entityManager->flush();

            return;
        }

        $agentEmail = $agent->getEmail();
        if ($agentEmail === null) {
            $notification->markAsFailed('Email agent non defini');
            $this->entityManager->flush();

            return;
        }

        try {
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($agentEmail)
                ->subject((string) $notification->getSujet())
                ->html((string) $notification->getContenu());

            $this->mailer->send($email);
            $notification->markAsSent();

            $this->logger?->info('Email envoye: {type} a {email}', [
                'type' => $notification->getType(),
                'email' => $agentEmail,
            ]);
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());

            $this->logger?->error('Echec envoi email: {type} a {email} - {error}', [
                'type' => $notification->getType(),
                'email' => $agentEmail,
                'error' => $e->getMessage(),
            ]);
        }

        $this->entityManager->flush();
    }

    /**
     * Envoie un email avec piece jointe ICS
     */
    private function envoyerEmailAvecIcs(Notification $notification, Reservation $reservation): void
    {
        $agent = $notification->getAgent();
        if ($agent === null) {
            $notification->markAsFailed('Agent non defini');
            $this->entityManager->flush();

            return;
        }

        $agentEmail = $agent->getEmail();
        if ($agentEmail === null) {
            $notification->markAsFailed('Email agent non defini');
            $this->entityManager->flush();

            return;
        }

        try {
            $icsContent = $this->icsGenerator->generate($reservation);

            $email = (new Email())
                ->from($this->senderEmail)
                ->to($agentEmail)
                ->subject((string) $notification->getSujet())
                ->html((string) $notification->getContenu())
                ->attach($icsContent, 'rdv.ics', 'text/calendar');

            $this->mailer->send($email);
            $notification->markAsSent();

            $this->logger?->info('Email avec ICS envoye: {type} a {email}', [
                'type' => $notification->getType(),
                'email' => $agentEmail,
            ]);
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());

            $this->logger?->error('Echec envoi email avec ICS: {type} a {email} - {error}', [
                'type' => $notification->getType(),
                'email' => $agentEmail,
                'error' => $e->getMessage(),
            ]);
        }

        $this->entityManager->flush();
    }
}
