<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

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

        return $reservation;
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

        $reservation->setCreneau($nouveauCreneau);
        $this->entityManager->flush();

        return $reservation;
    }

    /**
     * Annule une reservation
     */
    public function annuler(Reservation $reservation): void
    {
        $reservation->annuler();
        $this->entityManager->flush();
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
