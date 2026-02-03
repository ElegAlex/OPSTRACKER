<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Utilisateur;
use App\Repository\OperationRepository;

/**
 * Service d'agregation des durees d'intervention.
 */
class DureeInterventionService
{
    public function __construct(
        private readonly OperationRepository $operationRepository
    ) {
    }

    /**
     * Temps total d'une campagne (toutes operations terminees)
     */
    public function getTotalCampagne(Campagne $campagne): int
    {
        return $this->operationRepository->sumDureeInterventionByCampagne($campagne);
    }

    /**
     * Temps total d'un technicien sur une campagne
     */
    public function getTotalTechnicienCampagne(Utilisateur $technicien, Campagne $campagne): int
    {
        return $this->operationRepository->sumDureeInterventionByTechnicienAndCampagne(
            $technicien,
            $campagne
        );
    }

    /**
     * Temps total d'un technicien (toutes campagnes)
     * Retourne un tableau [campagne_id => minutes]
     *
     * @return array<int, int>
     */
    public function getTotalParCampagnePourTechnicien(Utilisateur $technicien): array
    {
        return $this->operationRepository->sumDureeInterventionGroupedByCampagne($technicien);
    }

    /**
     * Formate des minutes en "Xh" ou "XhYY"
     */
    public static function formatMinutes(int $minutes): string
    {
        if ($minutes === 0) {
            return '0h';
        }

        $heures = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($heures === 0) {
            return sprintf('%dmin', $mins);
        }

        if ($mins === 0) {
            return sprintf('%dh', $heures);
        }

        return sprintf('%dh%02d', $heures, $mins);
    }
}
