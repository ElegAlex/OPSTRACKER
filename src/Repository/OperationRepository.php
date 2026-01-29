<?php

namespace App\Repository;

use App\Entity\Operation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Operation>
 */
class OperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Operation::class);
    }

    /**
     * Trouve les operations d'une campagne
     *
     * @return Operation[]
     */
    public function findByCampagne(int $campagneId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les operations assignees a un technicien
     *
     * @return Operation[]
     */
    public function findByTechnicien(int $technicienId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.technicienAssigne = :technicien')
            ->setParameter('technicien', $technicienId)
            ->orderBy('o.datePlanifiee', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les operations par statut dans une campagne
     *
     * @return Operation[]
     */
    public function findByCampagneAndStatut(int $campagneId, string $statut): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.campagne = :campagne')
            ->andWhere('o.statut = :statut')
            ->setParameter('campagne', $campagneId)
            ->setParameter('statut', $statut)
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les operations par statut dans une campagne
     *
     * @return array<string, int>
     */
    public function countByStatutForCampagne(int $campagneId): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('o.statut, COUNT(o.id) as total')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
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
     * Trouve les operations d'un segment
     *
     * @return Operation[]
     */
    public function findBySegment(int $segmentId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.segment = :segment')
            ->setParameter('segment', $segmentId)
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche une operation par identifiant dans une campagne.
     * Cherche dans la premiere valeur de donneesPersonnalisees.
     *
     * RG-015 : Les donnees sont dans donneesPersonnalisees (JSONB)
     */
    public function findOneByIdentifiantAndCampagne(string $identifiant, int $campagneId): ?Operation
    {
        // Utiliser une requete native pour chercher dans JSONB
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT id FROM operation
                WHERE campagne_id = :campagne
                AND donnees_personnalisees::text ILIKE :search
                LIMIT 1";

        $result = $conn->executeQuery($sql, [
            'campagne' => $campagneId,
            'search' => '%' . $identifiant . '%',
        ])->fetchAssociative();

        if ($result === false) {
            return null;
        }

        return $this->find($result['id']);
    }

    /**
     * Trouve les operations avec filtres multiples
     * RG-015 : La recherche utilise donneesPersonnalisees (JSONB)
     *
     * @return Operation[]
     */
    public function findWithFilters(
        int $campagneId,
        ?string $statut = null,
        ?int $segmentId = null,
        ?int $technicienId = null,
        ?string $search = null
    ): array {
        // Si on a une recherche, utiliser une requete native SQL pour le JSONB
        if ($search !== null && $search !== '') {
            return $this->findWithFiltersNative($campagneId, $statut, $segmentId, $technicienId, $search);
        }

        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.segment', 's')
            ->leftJoin('o.technicienAssigne', 't')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('campagne', $campagneId);

        if ($statut !== null && $statut !== '') {
            $qb->andWhere('o.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($segmentId !== null) {
            if ($segmentId === 0) {
                $qb->andWhere('o.segment IS NULL');
            } else {
                $qb->andWhere('o.segment = :segment')
                   ->setParameter('segment', $segmentId);
            }
        }

        if ($technicienId !== null) {
            if ($technicienId === 0) {
                $qb->andWhere('o.technicienAssigne IS NULL');
            } else {
                $qb->andWhere('o.technicienAssigne = :technicien')
                   ->setParameter('technicien', $technicienId);
            }
        }

        return $qb
            ->addOrderBy('s.ordre', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Version native SQL de findWithFilters pour la recherche JSONB
     *
     * @return Operation[]
     */
    private function findWithFiltersNative(
        int $campagneId,
        ?string $statut,
        ?int $segmentId,
        ?int $technicienId,
        string $search
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT o.id FROM operation o
                LEFT JOIN segment s ON o.segment_id = s.id
                WHERE o.campagne_id = :campagne
                AND (o.donnees_personnalisees::text ILIKE :search OR LOWER(o.notes) LIKE LOWER(:search))";

        $params = [
            'campagne' => $campagneId,
            'search' => '%' . $search . '%',
        ];

        if ($statut !== null && $statut !== '') {
            $sql .= " AND o.statut = :statut";
            $params['statut'] = $statut;
        }

        if ($segmentId !== null) {
            if ($segmentId === 0) {
                $sql .= " AND o.segment_id IS NULL";
            } else {
                $sql .= " AND o.segment_id = :segment";
                $params['segment'] = $segmentId;
            }
        }

        if ($technicienId !== null) {
            if ($technicienId === 0) {
                $sql .= " AND o.technicien_assigne_id IS NULL";
            } else {
                $sql .= " AND o.technicien_assigne_id = :technicien";
                $params['technicien'] = $technicienId;
            }
        }

        $sql .= " ORDER BY COALESCE(s.ordre, 999) ASC, o.id ASC";

        $result = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        if (empty($result)) {
            return [];
        }

        $ids = array_column($result, 'id');

        return $this->createQueryBuilder('o')
            ->leftJoin('o.segment', 's')->addSelect('s')
            ->leftJoin('o.technicienAssigne', 't')->addSelect('t')
            ->where('o.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->addOrderBy('COALESCE(s.ordre, 999)', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les operations par segment dans une campagne
     *
     * @return array<int|null, int> [segment_id => count]
     */
    public function countBySegmentForCampagne(int $campagneId): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('IDENTITY(o.segment) as segment_id, COUNT(o.id) as total')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
            ->groupBy('o.segment')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $segmentId = $row['segment_id'] !== null ? (int) $row['segment_id'] : null;
            $counts[$segmentId] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Trouve les operations assignees a un technicien dans une campagne
     *
     * @return Operation[]
     */
    public function findByTechnicienAndCampagne(int $technicienId, int $campagneId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.technicienAssigne = :technicien')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('technicien', $technicienId)
            ->setParameter('campagne', $campagneId)
            ->orderBy('o.datePlanifiee', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche globale d'operations (T-907 / US-308)
     * RG-015 : Recherche dans donneesPersonnalisees (JSONB) et notes
     *
     * @return Operation[]
     */
    public function searchGlobal(string $query, int $limit = 50): array
    {
        $query = trim($query);
        if (empty($query)) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT o.id FROM operation o
                LEFT JOIN campagne c ON o.campagne_id = c.id
                WHERE c.statut != 'archivee'
                AND (o.donnees_personnalisees::text ILIKE :query OR LOWER(o.notes) LIKE LOWER(:query))
                ORDER BY c.nom ASC, o.id ASC
                LIMIT :limit";

        $result = $conn->executeQuery($sql, [
            'query' => '%' . $query . '%',
            'limit' => $limit,
        ], [
            'limit' => \PDO::PARAM_INT,
        ])->fetchAllAssociative();

        if (empty($result)) {
            return [];
        }

        $ids = array_column($result, 'id');

        // Charger les entites completes avec leurs relations
        return $this->createQueryBuilder('o')
            ->leftJoin('o.campagne', 'c')->addSelect('c')
            ->leftJoin('o.segment', 's')->addSelect('s')
            ->leftJoin('o.technicienAssigne', 't')->addSelect('t')
            ->where('o.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->addOrderBy('c.nom', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de resultats pour une recherche globale
     * RG-015 : Recherche dans donneesPersonnalisees (JSONB) et notes
     */
    public function countSearchGlobal(string $query): int
    {
        $query = trim($query);
        if (empty($query)) {
            return 0;
        }

        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT COUNT(o.id) FROM operation o
                LEFT JOIN campagne c ON o.campagne_id = c.id
                WHERE c.statut != 'archivee'
                AND (o.donnees_personnalisees::text ILIKE :query OR LOWER(o.notes) LIKE LOWER(:query))";

        return (int) $conn->executeQuery($sql, [
            'query' => '%' . $query . '%',
        ])->fetchOne();
    }

    /**
     * Compte les operations par statut pour un technicien (T-1003)
     *
     * @return array<string, int>
     */
    public function countByStatutForTechnicien(int $technicienId): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('o.statut, COUNT(o.id) as total')
            ->andWhere('o.technicienAssigne = :technicien')
            ->setParameter('technicien', $technicienId)
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
     * Compte le nombre total d'operations assignees a un technicien
     */
    public function countByTechnicien(int $technicienId): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.technicienAssigne = :technicien')
            ->setParameter('technicien', $technicienId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recupere l'activite recente d'un technicien (operations modifiees recemment)
     *
     * @return Operation[]
     */
    public function findRecentActivityByTechnicien(int $technicienId, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.campagne', 'c')
            ->andWhere('o.technicienAssigne = :technicien')
            ->setParameter('technicien', $technicienId)
            ->orderBy('o.updatedAt', 'DESC')
            ->addOrderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
