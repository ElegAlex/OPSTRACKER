<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Segment;
use App\Repository\CreneauRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des creneaux pour OpsTracker V2.
 *
 * Regles metier :
 * - RG-123 : Verrouillage J-X (defaut: 2 jours avant)
 * - RG-130 : Creation creneaux manuelle ou generation automatique
 * - RG-133 : Modification creneau = notification agents
 * - RG-134 : Suppression creneau = confirmation si reservations
 * - RG-135 : Association creneau <-> segment optionnelle
 */
class CreneauService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CreneauRepository $creneauRepository,
    ) {
    }

    /**
     * RG-130 : Cree un creneau manuellement
     */
    public function creer(Campagne $campagne, array $data): Creneau
    {
        $creneau = new Creneau();
        $creneau->setCampagne($campagne);
        $creneau->setDate($data['date']);
        $creneau->setHeureDebut($data['heureDebut']);
        $creneau->setHeureFin($data['heureFin']);
        $creneau->setCapacite($data['capacite'] ?? 1);
        $creneau->setLieu($data['lieu'] ?? null);
        $creneau->setSegment($data['segment'] ?? null);
        $creneau->setVerrouille($data['verrouille'] ?? false);

        $this->entityManager->persist($creneau);
        $this->entityManager->flush();

        return $creneau;
    }

    /**
     * RG-130 : Genere automatiquement une plage de creneaux
     *
     * @return Creneau[]
     */
    public function genererPlage(
        Campagne $campagne,
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
        int $dureeMinutes,
        int $capacite,
        ?string $lieu = null,
        ?Segment $segment = null,
        array $heuresParJour = []
    ): array {
        $creneaux = [];

        // Heures par defaut : 9h-12h et 14h-17h
        if (empty($heuresParJour)) {
            $heuresParJour = [
                ['debut' => '09:00', 'fin' => '12:00'],
                ['debut' => '14:00', 'fin' => '17:00'],
            ];
        }

        $currentDate = \DateTime::createFromInterface($dateDebut);
        $finDate = \DateTime::createFromInterface($dateFin);

        while ($currentDate <= $finDate) {
            // Ignorer les weekends
            $dayOfWeek = (int) $currentDate->format('N');
            if ($dayOfWeek >= 6) {
                $currentDate->modify('+1 day');
                continue;
            }

            foreach ($heuresParJour as $plage) {
                $heureDebut = \DateTime::createFromFormat('H:i', $plage['debut']);
                $heureFin = \DateTime::createFromFormat('H:i', $plage['fin']);

                while ($heureDebut < $heureFin) {
                    $slotFin = clone $heureDebut;
                    $slotFin->modify("+{$dureeMinutes} minutes");

                    if ($slotFin > $heureFin) {
                        break;
                    }

                    $creneau = new Creneau();
                    $creneau->setCampagne($campagne);
                    $creneau->setDate(clone $currentDate);
                    $creneau->setHeureDebut(clone $heureDebut);
                    $creneau->setHeureFin(clone $slotFin);
                    $creneau->setCapacite($capacite);
                    $creneau->setLieu($lieu);
                    $creneau->setSegment($segment);

                    $this->entityManager->persist($creneau);
                    $creneaux[] = $creneau;

                    $heureDebut = $slotFin;
                }
            }

            $currentDate->modify('+1 day');
        }

        $this->entityManager->flush();

        return $creneaux;
    }

    /**
     * RG-133 : Modifie un creneau (notification agents si reservations)
     */
    public function modifier(Creneau $creneau, array $data): Creneau
    {
        if (isset($data['date'])) {
            $creneau->setDate($data['date']);
        }
        if (isset($data['heureDebut'])) {
            $creneau->setHeureDebut($data['heureDebut']);
        }
        if (isset($data['heureFin'])) {
            $creneau->setHeureFin($data['heureFin']);
        }
        if (isset($data['capacite'])) {
            $creneau->setCapacite($data['capacite']);
        }
        if (isset($data['lieu'])) {
            $creneau->setLieu($data['lieu']);
        }
        if (isset($data['segment'])) {
            $creneau->setSegment($data['segment']);
        }
        if (isset($data['verrouille'])) {
            $creneau->setVerrouille($data['verrouille']);
        }

        $this->entityManager->flush();

        return $creneau;
    }

    /**
     * RG-134 : Supprime un creneau
     */
    public function supprimer(Creneau $creneau): void
    {
        $this->entityManager->remove($creneau);
        $this->entityManager->flush();
    }

    /**
     * Retourne les creneaux disponibles pour une campagne
     *
     * @return Creneau[]
     */
    public function getDisponibles(Campagne $campagne, ?Segment $segment = null): array
    {
        return $this->creneauRepository->findDisponibles($campagne, $segment);
    }

    /**
     * RG-123 : Verrouille automatiquement les creneaux J-X
     *
     * @return int Nombre de creneaux verrouilles
     */
    public function verrouillerAutomatique(int $joursAvant = 2): int
    {
        $creneaux = $this->creneauRepository->findAVerrouiller($joursAvant);
        $count = 0;

        foreach ($creneaux as $creneau) {
            $creneau->setVerrouille(true);
            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Verifie si un creneau a des reservations
     */
    public function hasReservations(Creneau $creneau): bool
    {
        return !$creneau->getReservations()->isEmpty();
    }

    /**
     * Compte le nombre de reservations confirmees pour un creneau
     */
    public function countReservationsConfirmees(Creneau $creneau): int
    {
        $count = 0;
        foreach ($creneau->getReservations() as $reservation) {
            if ($reservation->getStatut() === 'confirmee') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Retourne les statistiques de remplissage pour une campagne
     */
    public function getStatistiquesRemplissage(Campagne $campagne): array
    {
        return $this->creneauRepository->getStatistiquesRemplissage($campagne);
    }
}
