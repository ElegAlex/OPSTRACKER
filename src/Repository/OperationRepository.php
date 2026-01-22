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
            ->orderBy('o.matricule', 'ASC')
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
            ->orderBy('o.matricule', 'ASC')
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
            ->orderBy('o.matricule', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche une operation par matricule dans une campagne
     */
    public function findOneByMatriculeAndCampagne(string $matricule, int $campagneId): ?Operation
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.matricule = :matricule')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('matricule', $matricule)
            ->setParameter('campagne', $campagneId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les operations avec filtres multiples
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
                // Sans segment
                $qb->andWhere('o.segment IS NULL');
            } else {
                $qb->andWhere('o.segment = :segment')
                   ->setParameter('segment', $segmentId);
            }
        }

        if ($technicienId !== null) {
            if ($technicienId === 0) {
                // Non assigne
                $qb->andWhere('o.technicienAssigne IS NULL');
            } else {
                $qb->andWhere('o.technicienAssigne = :technicien')
                   ->setParameter('technicien', $technicienId);
            }
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('(LOWER(o.matricule) LIKE LOWER(:search) OR LOWER(o.nom) LIKE LOWER(:search))')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb
            ->addOrderBy('s.ordre', 'ASC')
            ->addOrderBy('o.matricule', 'ASC')
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
     * Recherche dans matricule, nom, notes sur toutes les campagnes non archivees
     *
     * @return Operation[]
     */
    public function searchGlobal(string $query, int $limit = 50): array
    {
        if (empty(trim($query))) {
            return [];
        }

        return $this->createQueryBuilder('o')
            ->leftJoin('o.campagne', 'c')
            ->leftJoin('o.segment', 's')
            ->leftJoin('o.technicienAssigne', 't')
            ->where('c.statut != :archived')
            ->andWhere(
                '(LOWER(o.matricule) LIKE LOWER(:query) OR ' .
                'LOWER(o.nom) LIKE LOWER(:query) OR ' .
                'LOWER(o.notes) LIKE LOWER(:query))'
            )
            ->setParameter('archived', 'archivee')
            ->setParameter('query', '%' . trim($query) . '%')
            ->addOrderBy('c.nom', 'ASC')
            ->addOrderBy('o.matricule', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de resultats pour une recherche globale
     */
    public function countSearchGlobal(string $query): int
    {
        if (empty(trim($query))) {
            return 0;
        }

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->leftJoin('o.campagne', 'c')
            ->where('c.statut != :archived')
            ->andWhere(
                '(LOWER(o.matricule) LIKE LOWER(:query) OR ' .
                'LOWER(o.nom) LIKE LOWER(:query) OR ' .
                'LOWER(o.notes) LIKE LOWER(:query))'
            )
            ->setParameter('archived', 'archivee')
            ->setParameter('query', '%' . trim($query) . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
