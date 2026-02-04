<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\ChecklistTemplate;
use App\Entity\Operation;
use App\Entity\TypeOperation;
use App\Entity\Utilisateur;
use App\Repository\CampagneRepository;
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
                'couleur' => Campagne::STATUTS_COULEURS[$statut],
            ];
        }

        return $result;
    }

    /**
     * RG-112 : Recupere les campagnes visibles par un utilisateur, groupees par statut.
     *
     * @return array<string, array{campagnes: Campagne[], count: int, label: string, couleur: string}>
     */
    public function getCampagnesVisiblesGroupedByStatut(Utilisateur $utilisateur, bool $isAdmin = false): array
    {
        $campagnesGrouped = $this->campagneRepository->findVisiblesGroupedByStatut($utilisateur, $isAdmin);

        $result = [];
        foreach (Campagne::STATUTS as $statut => $label) {
            $campagnes = $campagnesGrouped[$statut] ?? [];
            $result[$statut] = [
                'campagnes' => $campagnes,
                'count' => count($campagnes),
                'label' => $label,
                'couleur' => Campagne::STATUTS_COULEURS[$statut],
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
     *
     * @return array<int, string>
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

    /**
     * Retourne les donnees pour le graphique d'evolution (format Chart.js).
     * Affiche le cumul des realisations sur les 14 derniers jours.
     *
     * @return array{labels: string[], datasets: array<int, array<string, mixed>>}
     */
    public function getEvolutionTemporelle(Campagne $campagne): array
    {
        $operations = $campagne->getOperations();

        // Compter les operations realisees par date
        $parDate = [];
        foreach ($operations as $operation) {
            if ($operation->getStatut() === Operation::STATUT_REALISE && $operation->getDateRealisation()) {
                $date = $operation->getDateRealisation()->format('Y-m-d');
                $parDate[$date] = ($parDate[$date] ?? 0) + 1;
            }
        }

        // Generer les 14 derniers jours
        $labels = [];
        $values = [];
        $cumul = 0;

        $dateDebut = $campagne->getDateDebut();
        $dateFin = $campagne->getDateFin();
        $aujourd_hui = new \DateTimeImmutable();

        if ($dateDebut === null || $dateFin === null) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Operations realisees',
                        'data' => [],
                        'borderColor' => '#059669',
                        'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointRadius' => 3,
                        'pointBackgroundColor' => '#059669',
                    ],
                ],
            ];
        }

        // Limiter a la periode de la campagne, max 14 jours
        $debutPeriode = $aujourd_hui->modify('-13 days');
        $debut = $dateDebut > $debutPeriode ? $dateDebut : $debutPeriode;
        $fin = $dateFin < $aujourd_hui ? $dateFin : $aujourd_hui;

        $periode = new \DatePeriod($debut, new \DateInterval('P1D'), $fin->modify('+1 day'));

        foreach ($periode as $date) {
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            $cumul += ($parDate[$dateStr] ?? 0);
            $values[] = $cumul;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Operations realisees',
                    'data' => $values,
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 3,
                    'pointBackgroundColor' => '#059669',
                ],
            ],
        ];
    }

    /**
     * Retourne les donnees pour le graphique de repartition par statut (format Chart.js).
     *
     * @return array{labels: string[], datasets: array<int, array<string, mixed>>}
     */
    public function getRepartitionStatuts(Campagne $campagne): array
    {
        $operations = $campagne->getOperations();

        $parStatut = [
            'realise' => 0,
            'planifie' => 0,
            'reporte' => 0,
            'a_remedier' => 0,
            'a_planifier' => 0,
        ];

        foreach ($operations as $operation) {
            $statut = $operation->getStatut();
            if (isset($parStatut[$statut])) {
                $parStatut[$statut]++;
            }
        }

        // Filtrer les statuts avec des valeurs > 0
        $labels = [];
        $data = [];
        $colors = [];

        $statutConfig = [
            'realise' => ['label' => 'Realise', 'color' => '#059669'],
            'planifie' => ['label' => 'Planifie', 'color' => '#2563eb'],
            'reporte' => ['label' => 'Reporte', 'color' => '#d97706'],
            'a_remedier' => ['label' => 'A remedier', 'color' => '#dc2626'],
            'a_planifier' => ['label' => 'A planifier', 'color' => '#6b7280'],
        ];

        foreach ($parStatut as $statut => $count) {
            if ($count > 0 && array_key_exists($statut, $statutConfig)) {
                $labels[] = $statutConfig[$statut]['label'];
                $data[] = $count;
                $colors[] = $statutConfig[$statut]['color'];
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
        ];
    }
}
