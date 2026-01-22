<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\ChecklistTemplate;
use App\Entity\Operation;
use App\Entity\TypeOperation;
use App\Repository\CampagneRepository;
use App\Repository\OperationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Service metier pour la gestion des campagnes.
 *
 * Regles metier implementees :
 * - RG-010 : Gestion des 5 statuts campagne avec workflow
 * - RG-011 : Validation creation (nom + dates obligatoires)
 * - RG-014 : Association TypeOperation + ChecklistTemplate
 */
class CampagneService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CampagneRepository $campagneRepository,
        private readonly OperationRepository $operationRepository,
        #[Target('campagne')]
        private readonly WorkflowInterface $campagneWorkflow,
    ) {
    }

    /**
     * Recupere toutes les campagnes groupees par statut pour le portfolio.
     *
     * @return array<string, array{campagnes: Campagne[], count: int, label: string, couleur: string}>
     */
    public function getCampagnesGroupedByStatut(): array
    {
        $campagnesGrouped = $this->campagneRepository->findAllGroupedByStatut();

        $result = [];
        foreach (Campagne::STATUTS as $statut => $label) {
            $campagnes = $campagnesGrouped[$statut] ?? [];
            $result[$statut] = [
                'campagnes' => $campagnes,
                'count' => count($campagnes),
                'label' => $label,
                'couleur' => Campagne::STATUTS_COULEURS[$statut] ?? 'muted',
            ];
        }

        return $result;
    }

    /**
     * Cree une nouvelle campagne (etape 1/4).
     * RG-011 : Nom + Dates obligatoires
     */
    public function creerCampagne(
        string $nom,
        \DateTimeImmutable $dateDebut,
        \DateTimeImmutable $dateFin,
        ?string $description = null
    ): Campagne {
        $campagne = new Campagne();
        $campagne->setNom($nom);
        $campagne->setDateDebut($dateDebut);
        $campagne->setDateFin($dateFin);
        $campagne->setDescription($description);

        $this->entityManager->persist($campagne);
        $this->entityManager->flush();

        return $campagne;
    }

    /**
     * Configure le workflow et template d'une campagne (etape 4/4).
     * RG-014 : Association TypeOperation + ChecklistTemplate
     */
    public function configurerWorkflow(
        Campagne $campagne,
        ?TypeOperation $typeOperation,
        ?ChecklistTemplate $checklistTemplate
    ): Campagne {
        $campagne->setTypeOperation($typeOperation);
        $campagne->setChecklistTemplate($checklistTemplate);

        $this->entityManager->flush();

        return $campagne;
    }

    /**
     * Applique une transition de workflow a la campagne.
     * RG-010 : Transitions validees par le workflow Symfony
     */
    public function appliquerTransition(Campagne $campagne, string $transition): bool
    {
        if (!$this->campagneWorkflow->can($campagne, $transition)) {
            return false;
        }

        $this->campagneWorkflow->apply($campagne, $transition);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Recupere les transitions disponibles pour une campagne.
     */
    public function getTransitionsDisponibles(Campagne $campagne): array
    {
        return array_map(
            fn($t) => $t->getName(),
            $this->campagneWorkflow->getEnabledTransitions($campagne)
        );
    }

    /**
     * Calcule les statistiques d'une campagne.
     *
     * @return array{
     *     total: int,
     *     par_statut: array<string, int>,
     *     progression: float,
     *     realises: int
     * }
     */
    public function getStatistiquesCampagne(Campagne $campagne): array
    {
        $operations = $campagne->getOperations();
        $total = $operations->count();

        $parStatut = [];
        foreach (Operation::STATUTS as $statut => $label) {
            $parStatut[$statut] = 0;
        }

        $realises = 0;
        foreach ($operations as $operation) {
            $parStatut[$operation->getStatut()]++;
            if ($operation->getStatut() === Operation::STATUT_REALISE) {
                $realises++;
            }
        }

        $progression = $total > 0 ? round(($realises / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'par_statut' => $parStatut,
            'progression' => $progression,
            'realises' => $realises,
        ];
    }

    /**
     * Calcule les statistiques globales du portfolio.
     *
     * @return array{
     *     total_campagnes: int,
     *     actives: int,
     *     terminees: int,
     *     archivees: int,
     *     total_operations: int,
     *     operations_actives: int,
     *     progression_globale: float
     * }
     */
    public function getStatistiquesGlobales(): array
    {
        $campagnes = $this->campagneRepository->findAll();

        $total = count($campagnes);
        $actives = 0;
        $terminees = 0;
        $archivees = 0;
        $totalOperations = 0;
        $operationsActives = 0;
        $operationsRealisees = 0;

        foreach ($campagnes as $campagne) {
            switch ($campagne->getStatut()) {
                case Campagne::STATUT_EN_COURS:
                case Campagne::STATUT_A_VENIR:
                case Campagne::STATUT_PREPARATION:
                    $actives++;
                    break;
                case Campagne::STATUT_TERMINEE:
                    $terminees++;
                    break;
                case Campagne::STATUT_ARCHIVEE:
                    $archivees++;
                    break;
            }

            $operations = $campagne->getOperations();
            $totalOperations += $operations->count();

            // Operations actives = campagnes non archivees
            if ($campagne->getStatut() !== Campagne::STATUT_ARCHIVEE) {
                $operationsActives += $operations->count();

                foreach ($operations as $operation) {
                    if ($operation->getStatut() === Operation::STATUT_REALISE) {
                        $operationsRealisees++;
                    }
                }
            }
        }

        $progressionGlobale = $operationsActives > 0
            ? round(($operationsRealisees / $operationsActives) * 100, 1)
            : 0;

        return [
            'total_campagnes' => $total,
            'actives' => $actives,
            'terminees' => $terminees,
            'archivees' => $archivees,
            'total_operations' => $totalOperations,
            'operations_actives' => $operationsActives,
            'operations_realisees' => $operationsRealisees,
            'progression_globale' => $progressionGlobale,
        ];
    }
}
