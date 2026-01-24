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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

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
        private string $senderEmail = 'opstracker@cpam.local',
    ) {
    }

    /**
     * RG-140 : Envoie un email de confirmation avec ICS
     */
    public function envoyerConfirmation(Reservation $reservation): Notification
    {
        $agent = $reservation->getAgent();
        $creneau = $reservation->getCreneau();
        $campagne = $reservation->getCampagne();

        $sujet = sprintf(
            '[OpsTracker] Votre rendez-vous du %s est confirme',
            $creneau->getDate()->format('d/m/Y')
        );

        $contenu = $this->genererContenuConfirmation($reservation);

        $notification = $this->creerNotification(
            $agent,
            Notification::TYPE_CONFIRMATION,
            $sujet,
            $contenu,
            $reservation
        );

        // Envoyer l'email avec ICS
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

        // Verifier si un rappel a deja ete envoye
        if ($this->notificationRepository->hasRappelEnvoye($reservation)) {
            throw new \LogicException('Un rappel a deja ete envoye pour cette reservation.');
        }

        $sujet = sprintf(
            '[OpsTracker] Rappel : votre rendez-vous du %s',
            $creneau->getDate()->format('d/m/Y')
        );

        $contenu = $this->genererContenuRappel($reservation);

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
     * RG-142 : Envoie un email de modification
     */
    public function envoyerModification(Reservation $reservation, Creneau $ancienCreneau): Notification
    {
        $agent = $reservation->getAgent();
        $nouveauCreneau = $reservation->getCreneau();

        $sujet = sprintf(
            '[OpsTracker] Modification de votre rendez-vous du %s',
            $ancienCreneau->getDate()->format('d/m/Y')
        );

        $contenu = $this->genererContenuModification($reservation, $ancienCreneau);

        $notification = $this->creerNotification(
            $agent,
            Notification::TYPE_MODIFICATION,
            $sujet,
            $contenu,
            $reservation
        );

        // Envoyer l'email avec le nouvel ICS
        $this->envoyerEmailAvecIcs($notification, $reservation);

        return $notification;
    }

    /**
     * RG-143 : Envoie un email d'annulation
     */
    public function envoyerAnnulation(Reservation $reservation): Notification
    {
        $agent = $reservation->getAgent();
        $creneau = $reservation->getCreneau();

        $sujet = sprintf(
            '[OpsTracker] Votre rendez-vous du %s a ete annule',
            $creneau->getDate()->format('d/m/Y')
        );

        $contenu = $this->genererContenuAnnulation($reservation);

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
        $sujet = sprintf(
            '[OpsTracker] Invitation a reserver votre creneau - %s',
            $campagne->getNom()
        );

        $contenu = $this->genererContenuInvitation($agent, $campagne);

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
        try {
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($notification->getAgent()->getEmail())
                ->subject($notification->getSujet())
                ->html($notification->getContenu());

            $this->mailer->send($email);
            $notification->markAsSent();
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
        }

        $this->entityManager->flush();
    }

    /**
     * Envoie un email avec piece jointe ICS
     */
    private function envoyerEmailAvecIcs(Notification $notification, Reservation $reservation): void
    {
        try {
            $icsContent = $this->icsGenerator->generate($reservation);

            $email = (new Email())
                ->from($this->senderEmail)
                ->to($notification->getAgent()->getEmail())
                ->subject($notification->getSujet())
                ->html($notification->getContenu())
                ->attach($icsContent, 'rdv.ics', 'text/calendar');

            $this->mailer->send($email);
            $notification->markAsSent();
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
        }

        $this->entityManager->flush();
    }

    private function genererContenuConfirmation(Reservation $reservation): string
    {
        $creneau = $reservation->getCreneau();
        $campagne = $reservation->getCampagne();

        return sprintf(
            '<h2>Confirmation de votre rendez-vous</h2>
            <p>Bonjour %s,</p>
            <p>Votre rendez-vous pour la campagne <strong>%s</strong> est confirme.</p>
            <p><strong>Date :</strong> %s<br>
            <strong>Horaire :</strong> %s - %s<br>
            <strong>Lieu :</strong> %s</p>
            <p>Vous trouverez en piece jointe un fichier ICS pour ajouter ce rendez-vous a votre calendrier.</p>
            <p>Cordialement,<br>L\'equipe OpsTracker</p>',
            $reservation->getAgent()->getPrenom(),
            $campagne->getNom(),
            $creneau->getDate()->format('d/m/Y'),
            $creneau->getHeureDebut()->format('H:i'),
            $creneau->getHeureFin()->format('H:i'),
            $creneau->getLieu() ?? 'Non specifie'
        );
    }

    private function genererContenuRappel(Reservation $reservation): string
    {
        $creneau = $reservation->getCreneau();
        $campagne = $reservation->getCampagne();

        return sprintf(
            '<h2>Rappel de votre rendez-vous</h2>
            <p>Bonjour %s,</p>
            <p>Nous vous rappelons votre rendez-vous pour la campagne <strong>%s</strong>.</p>
            <p><strong>Date :</strong> %s<br>
            <strong>Horaire :</strong> %s - %s<br>
            <strong>Lieu :</strong> %s</p>
            <p>Cordialement,<br>L\'equipe OpsTracker</p>',
            $reservation->getAgent()->getPrenom(),
            $campagne->getNom(),
            $creneau->getDate()->format('d/m/Y'),
            $creneau->getHeureDebut()->format('H:i'),
            $creneau->getHeureFin()->format('H:i'),
            $creneau->getLieu() ?? 'Non specifie'
        );
    }

    private function genererContenuModification(Reservation $reservation, Creneau $ancienCreneau): string
    {
        $nouveauCreneau = $reservation->getCreneau();
        $campagne = $reservation->getCampagne();

        return sprintf(
            '<h2>Modification de votre rendez-vous</h2>
            <p>Bonjour %s,</p>
            <p>Votre rendez-vous pour la campagne <strong>%s</strong> a ete modifie.</p>
            <h3>Ancien creneau</h3>
            <p><strong>Date :</strong> %s<br>
            <strong>Horaire :</strong> %s - %s</p>
            <h3>Nouveau creneau</h3>
            <p><strong>Date :</strong> %s<br>
            <strong>Horaire :</strong> %s - %s<br>
            <strong>Lieu :</strong> %s</p>
            <p>Vous trouverez en piece jointe un fichier ICS pour mettre a jour votre calendrier.</p>
            <p>Cordialement,<br>L\'equipe OpsTracker</p>',
            $reservation->getAgent()->getPrenom(),
            $campagne->getNom(),
            $ancienCreneau->getDate()->format('d/m/Y'),
            $ancienCreneau->getHeureDebut()->format('H:i'),
            $ancienCreneau->getHeureFin()->format('H:i'),
            $nouveauCreneau->getDate()->format('d/m/Y'),
            $nouveauCreneau->getHeureDebut()->format('H:i'),
            $nouveauCreneau->getHeureFin()->format('H:i'),
            $nouveauCreneau->getLieu() ?? 'Non specifie'
        );
    }

    private function genererContenuAnnulation(Reservation $reservation): string
    {
        $creneau = $reservation->getCreneau();
        $campagne = $reservation->getCampagne();

        return sprintf(
            '<h2>Annulation de votre rendez-vous</h2>
            <p>Bonjour %s,</p>
            <p>Votre rendez-vous pour la campagne <strong>%s</strong> a ete annule.</p>
            <p><strong>Date :</strong> %s<br>
            <strong>Horaire :</strong> %s - %s</p>
            <p>Si vous souhaitez vous repositionner sur un autre creneau,
            veuillez contacter votre gestionnaire.</p>
            <p>Cordialement,<br>L\'equipe OpsTracker</p>',
            $reservation->getAgent()->getPrenom(),
            $campagne->getNom(),
            $creneau->getDate()->format('d/m/Y'),
            $creneau->getHeureDebut()->format('H:i'),
            $creneau->getHeureFin()->format('H:i')
        );
    }

    private function genererContenuInvitation(Agent $agent, Campagne $campagne): string
    {
        return sprintf(
            '<h2>Invitation a reserver votre creneau</h2>
            <p>Bonjour %s,</p>
            <p>Vous etes invite(e) a choisir un creneau pour la campagne <strong>%s</strong>.</p>
            <p><strong>Periode :</strong> du %s au %s</p>
            <p>Veuillez vous connecter a OpsTracker pour selectionner votre creneau.</p>
            <p>Cordialement,<br>L\'equipe OpsTracker</p>',
            $agent->getPrenom(),
            $campagne->getNom(),
            $campagne->getDateDebut()->format('d/m/Y'),
            $campagne->getDateFin()->format('d/m/Y')
        );
    }
}
