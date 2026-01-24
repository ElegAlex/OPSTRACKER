<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des reservations pour OpsTracker V2.
 *
 * Regles metier :
 * - RG-121 : Un agent = un seul creneau par campagne
 * - RG-122 : Confirmation automatique = email + ICS
 * - RG-123 : Verrouillage J-X
 * - RG-125 : Tracabilite : enregistrer qui a positionne
 * - RG-126 : Notification agent si positionne par tiers
 */
class ReservationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReservationRepository $reservationRepository,
        private ?NotificationService $notificationService = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * RG-121 : Reserve un creneau pour un agent
     *
     * @throws \LogicException Si l'agent a deja une reservation ou si le creneau est complet/verrouille
     */
    public function reserver(
        Agent $agent,
        Creneau $creneau,
        string $typePositionnement = Reservation::TYPE_AGENT,
        ?Utilisateur $positionnePar = null
    ): Reservation {
        $campagne = $creneau->getCampagne();

        // RG-121 : Verifier l'unicite agent/campagne
        $existante = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);
        if ($existante !== null) {
            throw new \LogicException(
                sprintf('L\'agent %s a deja une reservation pour cette campagne.', $agent->getNomComplet())
            );
        }

        // Verifier que le creneau n'est pas complet
        if ($creneau->isComplet()) {
            throw new \LogicException('Ce creneau est complet.');
        }

        // RG-123 : Verifier que le creneau n'est pas verrouille
        if ($creneau->isVerrouillePourDate()) {
            throw new \LogicException('Ce creneau est verrouille.');
        }

        // Creer la reservation
        $reservation = new Reservation();
        $reservation->setAgent($agent);
        $reservation->setCreneau($creneau);
        $reservation->setCampagne($campagne);
        $reservation->setTypePositionnement($typePositionnement);

        // RG-125 : Tracabilite
        if ($positionnePar !== null) {
            $reservation->setPositionnePar($positionnePar);
        }

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        // RG-122 : Confirmation automatique = email + ICS
        // RG-126 : Notification si positionne par tiers
        $this->envoyerNotificationConfirmation($reservation);

        return $reservation;
    }

    /**
     * RG-122, RG-126 : Envoie la notification de confirmation.
     */
    private function envoyerNotificationConfirmation(Reservation $reservation): void
    {
        if ($this->notificationService === null) {
            return;
        }

        try {
            $this->notificationService->envoyerConfirmation($reservation);
        } catch (\Exception $e) {
            // Log error but don't fail the reservation
            $this->logger?->error('Erreur envoi notification confirmation', [
                'reservation_id' => $reservation->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Modifie une reservation (change de creneau)
     *
     * @throws \LogicException Si le nouveau creneau est complet/verrouille
     */
    public function modifier(Reservation $reservation, Creneau $nouveauCreneau): Reservation
    {
        // Verifier que le nouveau creneau n'est pas complet
        if ($nouveauCreneau->isComplet()) {
            throw new \LogicException('Le nouveau creneau est complet.');
        }

        // RG-123 : Verifier que le nouveau creneau n'est pas verrouille
        if ($nouveauCreneau->isVerrouillePourDate()) {
            throw new \LogicException('Le nouveau creneau est verrouille.');
        }

        // Sauvegarder l'ancien creneau pour la notification
        $ancienCreneau = $reservation->getCreneau();

        $reservation->setCreneau($nouveauCreneau);
        $this->entityManager->flush();

        // RG-126 : Notification de modification
        $this->envoyerNotificationModification($reservation, $ancienCreneau);

        return $reservation;
    }

    /**
     * RG-126 : Envoie la notification de modification.
     */
    private function envoyerNotificationModification(Reservation $reservation, Creneau $ancienCreneau): void
    {
        if ($this->notificationService === null) {
            return;
        }

        try {
            $this->notificationService->envoyerModification($reservation, $ancienCreneau);
        } catch (\Exception $e) {
            // Log error but don't fail the modification
            $this->logger?->error('Erreur envoi notification modification', [
                'reservation_id' => $reservation->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Annule une reservation
     */
    public function annuler(Reservation $reservation): void
    {
        $reservation->annuler();
        $this->entityManager->flush();

        // Notification d'annulation
        $this->envoyerNotificationAnnulation($reservation);
    }

    /**
     * Envoie la notification d'annulation.
     */
    private function envoyerNotificationAnnulation(Reservation $reservation): void
    {
        if ($this->notificationService === null) {
            return;
        }

        try {
            $this->notificationService->envoyerAnnulation($reservation);
        } catch (\Exception $e) {
            // Log error but don't fail the cancellation
            $this->logger?->error('Erreur envoi notification annulation', [
                'reservation_id' => $reservation->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * RG-121 : Trouve la reservation d'un agent pour une campagne
     */
    public function getByAgent(Agent $agent, Campagne $campagne): ?Reservation
    {
        return $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);
    }

    /**
     * RG-124 : Trouve les reservations des agents d'un manager
     *
     * @return Reservation[]
     */
    public function getByManager(Agent $manager, Campagne $campagne): array
    {
        return $this->reservationRepository->findByManagerAndCampagne($manager, $campagne);
    }

    /**
     * Trouve les reservations d'une campagne
     *
     * @return Reservation[]
     */
    public function getByCampagne(Campagne $campagne): array
    {
        return $this->reservationRepository->findByCampagne($campagne);
    }

    /**
     * Trouve les reservations d'un creneau
     *
     * @return Reservation[]
     */
    public function getByCreneau(Creneau $creneau): array
    {
        return $this->reservationRepository->findByCreneau($creneau);
    }

    /**
     * Verifie si un agent a une reservation pour une campagne
     */
    public function hasReservation(Agent $agent, Campagne $campagne): bool
    {
        return $this->reservationRepository->findByAgentAndCampagne($agent, $campagne) !== null;
    }

    /**
     * Compte les reservations par type de positionnement
     *
     * @return array<string, int>
     */
    public function countByTypePositionnement(Campagne $campagne): array
    {
        return $this->reservationRepository->countByTypePositionnement($campagne);
    }
}
