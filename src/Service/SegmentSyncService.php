<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Segment;
use App\Repository\SegmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de synchronisation des segments depuis une colonne CSV.
 *
 * Extrait les valeurs uniques de la colonne designee (colonneSegment)
 * et cree les segments correspondants.
 */
class SegmentSyncService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SegmentRepository $segmentRepository,
    ) {
    }

    /**
     * Synchronise les segments a partir de la colonne designee.
     *
     * @return int Nombre de segments crees
     */
    public function syncFromColonne(Campagne $campagne): int
    {
        $colonneSegment = $campagne->getColonneSegment();

        if (!$colonneSegment) {
            return 0;
        }

        // Recuperer les valeurs uniques de la colonne
        $operations = $campagne->getOperations();
        $valeurs = [];

        foreach ($operations as $operation) {
            $donnees = $operation->getDonneesPersonnalisees();
            if (isset($donnees[$colonneSegment]) && !empty($donnees[$colonneSegment])) {
                $valeur = trim((string) $donnees[$colonneSegment]);
                if ($valeur !== '' && !in_array($valeur, $valeurs, true)) {
                    $valeurs[] = $valeur;
                }
            }
        }

        // Trier les valeurs pour un ordre coherent
        sort($valeurs);

        // Recuperer les segments existants
        $segmentsExistants = $this->segmentRepository->findBy(['campagne' => $campagne]);
        $nomsExistants = array_map(fn ($s) => $s->getNom(), $segmentsExistants);

        // Creer les segments manquants
        $created = 0;
        foreach ($valeurs as $index => $valeur) {
            if (!in_array($valeur, $nomsExistants, true)) {
                $segment = new Segment();
                $segment->setNom($valeur);
                $segment->setCampagne($campagne);
                $segment->setCouleur($this->generateColor($index));
                $segment->setOrdre($index);
                $this->em->persist($segment);
                $created++;
            }
        }

        $this->em->flush();

        // Assigner les operations aux segments
        $this->assignOperationsToSegments($campagne, $colonneSegment);

        return $created;
    }

    /**
     * Assigne chaque operation au segment correspondant a sa valeur de colonne.
     */
    private function assignOperationsToSegments(Campagne $campagne, string $colonneSegment): void
    {
        // Recharger les segments apres creation
        $segments = $this->segmentRepository->findBy(['campagne' => $campagne]);
        $segmentsByNom = [];
        foreach ($segments as $segment) {
            $segmentsByNom[$segment->getNom()] = $segment;
        }

        foreach ($campagne->getOperations() as $operation) {
            $donnees = $operation->getDonneesPersonnalisees();
            $valeur = isset($donnees[$colonneSegment]) ? trim((string) $donnees[$colonneSegment]) : null;

            if ($valeur && isset($segmentsByNom[$valeur])) {
                $operation->setSegment($segmentsByNom[$valeur]);
            } else {
                // Operation sans valeur de segment -> pas de segment
                $operation->setSegment(null);
            }
        }

        $this->em->flush();
    }

    /**
     * Genere une couleur Tailwind pour un segment.
     */
    private function generateColor(int $index): string
    {
        $colors = [
            'primary',
            'success',
            'warning',
            'danger',
            'info',
            'muted',
            'complete',
        ];

        return $colors[$index % count($colors)];
    }
}
