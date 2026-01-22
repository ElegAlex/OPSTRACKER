<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Prerequis;
use App\Entity\Segment;
use App\Repository\PrerequisRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour la gestion des prerequis de campagne.
 *
 * Regles metier :
 * - RG-090 : Prerequis globaux de campagne avec statut (A faire / En cours / Fait)
 * - RG-091 : Prerequis specifiques a un segment
 *
 * Les prerequis sont des indicateurs declaratifs, NON bloquants.
 */
class PrerequisService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PrerequisRepository $prerequisRepository,
    ) {
    }

    /**
     * RG-090 : Recupere tous les prerequis globaux d'une campagne avec progression
     *
     * @return array{prerequis: Prerequis[], progression: array{total: int, faits: int, pourcentage: int}}
     */
    public function getPrerequisGlobaux(Campagne $campagne): array
    {
        return [
            'prerequis' => $this->prerequisRepository->findGlobauxByCampagne($campagne),
            'progression' => $this->prerequisRepository->getProgressionGlobale($campagne),
        ];
    }

    /**
     * RG-091 : Recupere tous les prerequis par segment avec progression
     *
     * @return array<int, array{segment: Segment, prerequis: Prerequis[], progression: array{total: int, faits: int, pourcentage: int}}>
     */
    public function getPrerequisParSegment(Campagne $campagne): array
    {
        $segments = $campagne->getSegments();
        $prerequisParSegment = $this->prerequisRepository->findAllBySegmentForCampagne($campagne);
        $progressions = $this->prerequisRepository->getProgressionParSegment($campagne);

        $result = [];
        foreach ($segments as $segment) {
            $segmentId = $segment->getId();
            $result[$segmentId] = [
                'segment' => $segment,
                'prerequis' => $prerequisParSegment[$segmentId] ?? [],
                'progression' => $progressions[$segmentId] ?? ['total' => 0, 'faits' => 0, 'pourcentage' => 0],
            ];
        }

        return $result;
    }

    /**
     * RG-090 : Cree un nouveau prerequis global pour une campagne
     */
    public function creerPrerequisGlobal(
        Campagne $campagne,
        string $libelle,
        ?string $responsable = null,
        ?\DateTimeImmutable $dateCible = null
    ): Prerequis {
        $prerequis = new Prerequis();
        $prerequis->setCampagne($campagne);
        $prerequis->setLibelle($libelle);
        $prerequis->setResponsable($responsable);
        $prerequis->setDateCible($dateCible);
        $prerequis->setOrdre($this->prerequisRepository->getNextOrdre($campagne));

        $this->em->persist($prerequis);
        $this->em->flush();

        return $prerequis;
    }

    /**
     * RG-091 : Cree un nouveau prerequis pour un segment
     */
    public function creerPrerequisSegment(
        Segment $segment,
        string $libelle,
        ?string $responsable = null,
        ?\DateTimeImmutable $dateCible = null
    ): Prerequis {
        $prerequis = new Prerequis();
        $prerequis->setCampagne($segment->getCampagne());
        $prerequis->setSegment($segment);
        $prerequis->setLibelle($libelle);
        $prerequis->setResponsable($responsable);
        $prerequis->setDateCible($dateCible);
        $prerequis->setOrdre($this->prerequisRepository->getNextOrdre($segment->getCampagne(), $segment));

        $this->em->persist($prerequis);
        $this->em->flush();

        return $prerequis;
    }

    /**
     * Met a jour le statut d'un prerequis
     */
    public function updateStatut(Prerequis $prerequis, string $nouveauStatut): Prerequis
    {
        $prerequis->setStatut($nouveauStatut);
        $this->em->flush();

        return $prerequis;
    }

    /**
     * Met a jour un prerequis
     */
    public function update(
        Prerequis $prerequis,
        string $libelle,
        ?string $responsable = null,
        ?\DateTimeImmutable $dateCible = null
    ): Prerequis {
        $prerequis->setLibelle($libelle);
        $prerequis->setResponsable($responsable);
        $prerequis->setDateCible($dateCible);

        $this->em->flush();

        return $prerequis;
    }

    /**
     * Supprime un prerequis
     */
    public function supprimer(Prerequis $prerequis): void
    {
        $this->em->remove($prerequis);
        $this->em->flush();
    }

    /**
     * Recupere les donnees completes des prerequis pour l'onglet prerequis
     */
    public function getDonneesOngletPrerequis(Campagne $campagne): array
    {
        $globaux = $this->getPrerequisGlobaux($campagne);
        $parSegment = $this->getPrerequisParSegment($campagne);
        $enRetard = $this->prerequisRepository->countEnRetard($campagne);

        // Calcul du total general
        $totalGlobaux = $globaux['progression']['total'];
        $faitsGlobaux = $globaux['progression']['faits'];

        $totalSegments = 0;
        $faitsSegments = 0;
        foreach ($parSegment as $data) {
            $totalSegments += $data['progression']['total'];
            $faitsSegments += $data['progression']['faits'];
        }

        $totalGeneral = $totalGlobaux + $totalSegments;
        $faitsGeneral = $faitsGlobaux + $faitsSegments;
        $pourcentageGeneral = $totalGeneral > 0 ? (int) round(($faitsGeneral / $totalGeneral) * 100) : 0;

        return [
            'globaux' => $globaux,
            'parSegment' => $parSegment,
            'enRetard' => $enRetard,
            'progressionGenerale' => [
                'total' => $totalGeneral,
                'faits' => $faitsGeneral,
                'pourcentage' => $pourcentageGeneral,
            ],
        ];
    }

    /**
     * Verifie si un segment a des prerequis non faits (pour afficher alerte)
     */
    public function hasPrerequisNonFaits(Segment $segment): bool
    {
        $progression = $this->prerequisRepository->getProgressionSegment($segment);

        return $progression['total'] > 0 && $progression['faits'] < $progression['total'];
    }

    /**
     * Verifie si un segment a des prerequis a 0%
     */
    public function hasPrerequisAZero(Segment $segment): bool
    {
        $progression = $this->prerequisRepository->getProgressionSegment($segment);

        return $progression['total'] > 0 && $progression['faits'] === 0;
    }
}
