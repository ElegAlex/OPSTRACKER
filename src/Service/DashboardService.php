<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Repository\CampagneRepository;
use App\Repository\OperationRepository;
use App\Repository\SegmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service metier pour les dashboards de Sophie (Gestionnaire IT).
 *
 * Regles metier implementees :
 * - RG-040 : Affichage temps reel via Turbo Streams
 * - RG-080 : Triple signalisation (icone + couleur + texte)
 * - RG-081 : Contraste RGAA >= 4.5:1
 */
class DashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CampagneRepository $campagneRepository,
        private readonly OperationRepository $operationRepository,
        private readonly SegmentRepository $segmentRepository,
    ) {
    }

    /**
     * Recupere les statistiques KPI d'une campagne (widgets).
     * T-705 : Compteurs par statut
     *
     * @return array{
     *     total: int,
     *     realise: array{count: int, percentage: float, today: int, icon: string, color: string, label: string},
     *     planifie: array{count: int, percentage: float, icon: string, color: string, label: string},
     *     reporte: array{count: int, percentage: float, week: int, icon: string, color: string, label: string},
     *     a_remedier: array{count: int, percentage: float, icon: string, color: string, label: string},
     *     en_cours: array{count: int, percentage: float, icon: string, color: string, label: string},
     *     a_planifier: array{count: int, percentage: float, icon: string, color: string, label: string}
     * }
     */
    public function getKpiCampagne(Campagne $campagne): array
    {
        $counts = $this->operationRepository->countByStatutForCampagne($campagne->getId());
        $total = array_sum($counts);

        // Compteurs du jour
        $today = new \DateTimeImmutable('today');
        $todayRealized = $this->countOperationsRealisedSince($campagne, $today);

        // Compteurs de la semaine
        $weekStart = new \DateTimeImmutable('monday this week');
        $weekReported = $this->countOperationsReportedSince($campagne, $weekStart);

        $percentage = fn(int $count): float => $total > 0 ? round(($count / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'realise' => [
                'count' => $counts[Operation::STATUT_REALISE] ?? 0,
                'percentage' => $percentage($counts[Operation::STATUT_REALISE] ?? 0),
                'today' => $todayRealized,
                'icon' => 'check-circle',
                'color' => 'success',
                'label' => 'Réalisé',
            ],
            'planifie' => [
                'count' => ($counts[Operation::STATUT_PLANIFIE] ?? 0) + ($counts[Operation::STATUT_EN_COURS] ?? 0),
                'percentage' => $percentage(($counts[Operation::STATUT_PLANIFIE] ?? 0) + ($counts[Operation::STATUT_EN_COURS] ?? 0)),
                'icon' => 'clock',
                'color' => 'primary',
                'label' => 'Planifié',
            ],
            'reporte' => [
                'count' => $counts[Operation::STATUT_REPORTE] ?? 0,
                'percentage' => $percentage($counts[Operation::STATUT_REPORTE] ?? 0),
                'week' => $weekReported,
                'icon' => 'pause-circle',
                'color' => 'warning',
                'label' => 'Reporté',
            ],
            'a_remedier' => [
                'count' => $counts[Operation::STATUT_A_REMEDIER] ?? 0,
                'percentage' => $percentage($counts[Operation::STATUT_A_REMEDIER] ?? 0),
                'icon' => 'alert-triangle',
                'color' => 'danger',
                'label' => 'À remédier',
            ],
            'en_cours' => [
                'count' => $counts[Operation::STATUT_EN_COURS] ?? 0,
                'percentage' => $percentage($counts[Operation::STATUT_EN_COURS] ?? 0),
                'icon' => 'play-circle',
                'color' => 'primary',
                'label' => 'En cours',
            ],
            'a_planifier' => [
                'count' => $counts[Operation::STATUT_A_PLANIFIER] ?? 0,
                'percentage' => $percentage($counts[Operation::STATUT_A_PLANIFIER] ?? 0),
                'icon' => 'calendar',
                'color' => 'muted',
                'label' => 'À planifier',
            ],
        ];
    }

    /**
     * Recupere la progression par segment d'une campagne.
     * T-702 : Progression par segment
     *
     * @return array<int, array{
     *     segment: Segment,
     *     total: int,
     *     realise: int,
     *     planifie: int,
     *     reporte: int,
     *     a_remedier: int,
     *     progression: float,
     *     is_late: bool,
     *     counts_by_statut: array<string, int>
     * }>
     */
    public function getProgressionParSegment(Campagne $campagne): array
    {
        $segments = $this->segmentRepository->findByCampagne($campagne->getId());
        $result = [];

        foreach ($segments as $segment) {
            $counts = $this->countByStatutForSegment($segment);
            $total = array_sum($counts);
            $realise = $counts[Operation::STATUT_REALISE] ?? 0;
            $progression = $total > 0 ? round(($realise / $total) * 100, 1) : 0;

            // Un segment est en retard si la progression est < 50% et qu'il a des problèmes
            $aRemedier = $counts[Operation::STATUT_A_REMEDIER] ?? 0;
            $reporte = $counts[Operation::STATUT_REPORTE] ?? 0;
            $isLate = $progression < 50 && ($aRemedier > 0 || $reporte > ($total * 0.1));

            $result[] = [
                'segment' => $segment,
                'total' => $total,
                'realise' => $realise,
                'planifie' => ($counts[Operation::STATUT_PLANIFIE] ?? 0) + ($counts[Operation::STATUT_EN_COURS] ?? 0),
                'reporte' => $reporte,
                'a_remedier' => $aRemedier,
                'progression' => $progression,
                'is_late' => $isLate,
                'counts_by_statut' => $counts,
            ];
        }

        return $result;
    }

    /**
     * Recupere l'activite recente d'une campagne.
     *
     * @return array<int, array{
     *     operation: Operation,
     *     type: string,
     *     timestamp: \DateTimeImmutable,
     *     technicien: ?string
     * }>
     */
    public function getActiviteRecente(Campagne $campagne, int $limit = 10): array
    {
        $operations = $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from(Operation::class, 'o')
            ->leftJoin('o.technicienAssigne', 't')
            ->where('o.campagne = :campagne')
            ->andWhere('o.updatedAt IS NOT NULL OR o.dateRealisation IS NOT NULL')
            ->setParameter('campagne', $campagne)
            ->orderBy('COALESCE(o.updatedAt, o.dateRealisation, o.createdAt)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($operations as $operation) {
            $type = match ($operation->getStatut()) {
                Operation::STATUT_REALISE => 'termine',
                Operation::STATUT_REPORTE => 'reporte',
                Operation::STATUT_A_REMEDIER => 'probleme',
                Operation::STATUT_EN_COURS => 'demarre',
                default => 'modifie',
            };

            $timestamp = $operation->getDateRealisation()
                ?? $operation->getUpdatedAt()
                ?? $operation->getCreatedAt();

            $technicien = $operation->getTechnicienAssigne();

            $result[] = [
                'operation' => $operation,
                'type' => $type,
                'timestamp' => $timestamp,
                'technicien' => $technicien?->getPrenom() . ' ' . substr($technicien?->getNom() ?? '', 0, 1) . '.',
            ];
        }

        return $result;
    }

    /**
     * Recupere les statistiques de l'equipe assignee a une campagne.
     *
     * @return array<int, array{
     *     technicien: \App\Entity\Utilisateur,
     *     initiales: string,
     *     assignees: int,
     *     realisees: int,
     *     progression: float
     * }>
     */
    public function getStatistiquesEquipe(Campagne $campagne): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $stats = $qb
            ->select('t.id, t.prenom, t.nom, COUNT(o.id) as assignees,
                      SUM(CASE WHEN o.statut = :realise THEN 1 ELSE 0 END) as realisees')
            ->from(Operation::class, 'o')
            ->join('o.technicienAssigne', 't')
            ->where('o.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->setParameter('realise', Operation::STATUT_REALISE)
            ->groupBy('t.id, t.prenom, t.nom')
            ->orderBy('realisees', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($stats as $stat) {
            $assignees = (int) $stat['assignees'];
            $realisees = (int) $stat['realisees'];
            $progression = $assignees > 0 ? round(($realisees / $assignees) * 100, 0) : 0;

            $result[] = [
                'id' => $stat['id'],
                'prenom' => $stat['prenom'],
                'nom' => $stat['nom'],
                'initiales' => strtoupper(substr($stat['prenom'] ?? '', 0, 1) . substr($stat['nom'] ?? '', 0, 1)),
                'assignees' => $assignees,
                'realisees' => $realisees,
                'progression' => $progression,
            ];
        }

        return $result;
    }

    /**
     * Recupere les statistiques globales pour le dashboard multi-campagnes.
     * T-703 : Dashboard global
     *
     * @return array{
     *     campagnes: array<int, array{
     *         campagne: Campagne,
     *         kpi: array,
     *         segments_count: int,
     *         techniciens_count: int
     *     }>,
     *     totaux: array{
     *         campagnes: int,
     *         operations: int,
     *         realise: int,
     *         planifie: int,
     *         reporte: int,
     *         a_remedier: int,
     *         progression: float
     *     }
     * }
     */
    /**
     * T-1307 : Dashboard global avec filtrage par statut de campagne
     *
     * @param string[]|null $statutsFilter Statuts de campagne a afficher (null = tous les actifs)
     */
    public function getDashboardGlobal(?array $statutsFilter = null): array
    {
        // Si pas de filtre, utiliser les statuts actifs par defaut
        if ($statutsFilter === null || empty($statutsFilter)) {
            $campagnes = $this->campagneRepository->findBy(
                ['statut' => [Campagne::STATUT_EN_COURS, Campagne::STATUT_A_VENIR, Campagne::STATUT_PREPARATION]],
                ['dateDebut' => 'ASC']
            );
        } else {
            $campagnes = $this->campagneRepository->findByStatuts($statutsFilter);
        }

        $campagnesData = [];
        $totaux = [
            'campagnes' => count($campagnes),
            'operations' => 0,
            'realise' => 0,
            'planifie' => 0,
            'reporte' => 0,
            'a_remedier' => 0,
        ];

        foreach ($campagnes as $campagne) {
            $kpi = $this->getKpiCampagne($campagne);
            $segments = $this->segmentRepository->findByCampagne($campagne->getId());
            $techniciens = $this->countTechniciensAssignes($campagne);

            $campagnesData[] = [
                'campagne' => $campagne,
                'kpi' => $kpi,
                'segments_count' => count($segments),
                'techniciens_count' => $techniciens,
            ];

            $totaux['operations'] += $kpi['total'];
            $totaux['realise'] += $kpi['realise']['count'];
            $totaux['planifie'] += $kpi['planifie']['count'];
            $totaux['reporte'] += $kpi['reporte']['count'];
            $totaux['a_remedier'] += $kpi['a_remedier']['count'];
        }

        $totaux['progression'] = $totaux['operations'] > 0
            ? round(($totaux['realise'] / $totaux['operations']) * 100, 1)
            : 0;

        return [
            'campagnes' => $campagnesData,
            'totaux' => $totaux,
            'filtres' => $statutsFilter,
        ];
    }

    /**
     * Recupere les donnees pour un refresh Turbo Stream.
     * T-704 : Temps reel
     */
    public function getRefreshData(Campagne $campagne): array
    {
        return [
            'kpi' => $this->getKpiCampagne($campagne),
            'segments' => $this->getProgressionParSegment($campagne),
            'activite' => $this->getActiviteRecente($campagne, 5),
            'timestamp' => new \DateTimeImmutable(),
        ];
    }

    /**
     * Compte les operations realisees depuis une date.
     */
    private function countOperationsRealisedSince(Campagne $campagne, \DateTimeImmutable $since): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Operation::class, 'o')
            ->where('o.campagne = :campagne')
            ->andWhere('o.statut = :statut')
            ->andWhere('o.dateRealisation >= :since')
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', Operation::STATUT_REALISE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les operations reportees depuis une date.
     */
    private function countOperationsReportedSince(Campagne $campagne, \DateTimeImmutable $since): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Operation::class, 'o')
            ->where('o.campagne = :campagne')
            ->andWhere('o.statut = :statut')
            ->andWhere('o.updatedAt >= :since')
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', Operation::STATUT_REPORTE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les operations par statut pour un segment.
     *
     * @return array<string, int>
     */
    private function countByStatutForSegment(Segment $segment): array
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('o.statut, COUNT(o.id) as total')
            ->from(Operation::class, 'o')
            ->where('o.segment = :segment')
            ->setParameter('segment', $segment)
            ->groupBy('o.statut')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach (Operation::STATUTS as $statut => $label) {
            $counts[$statut] = 0;
        }
        foreach ($result as $row) {
            $counts[$row['statut']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Compte le nombre de techniciens distincts assignes a une campagne.
     */
    private function countTechniciensAssignes(Campagne $campagne): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT o.technicienAssigne)')
            ->from(Operation::class, 'o')
            ->where('o.campagne = :campagne')
            ->andWhere('o.technicienAssigne IS NOT NULL')
            ->setParameter('campagne', $campagne)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
