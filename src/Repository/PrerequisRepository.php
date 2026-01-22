<?php

namespace App\Repository;

use App\Entity\Campagne;
use App\Entity\Prerequis;
use App\Entity\Segment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entite Prerequis.
 *
 * @extends ServiceEntityRepository<Prerequis>
 */
class PrerequisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prerequis::class);
    }

    /**
     * RG-090 : Trouve tous les prerequis globaux d'une campagne (sans segment)
     *
     * @return Prerequis[]
     */
    public function findGlobauxByCampagne(Campagne $campagne): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.campagne = :campagne')
            ->andWhere('p.segment IS NULL')
            ->setParameter('campagne', $campagne)
            ->orderBy('p.ordre', 'ASC')
            ->addOrderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * RG-091 : Trouve tous les prerequis d'un segment
     *
     * @return Prerequis[]
     */
    public function findBySegment(Segment $segment): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.segment = :segment')
            ->setParameter('segment', $segment)
            ->orderBy('p.ordre', 'ASC')
            ->addOrderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les prerequis par segment pour une campagne
     *
     * @return array<int, Prerequis[]> Array indexe par segment_id
     */
    public function findAllBySegmentForCampagne(Campagne $campagne): array
    {
        $prerequis = $this->createQueryBuilder('p')
            ->where('p.campagne = :campagne')
            ->andWhere('p.segment IS NOT NULL')
            ->setParameter('campagne', $campagne)
            ->orderBy('p.ordre', 'ASC')
            ->addOrderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($prerequis as $p) {
            $segmentId = $p->getSegment()->getId();
            if (!isset($result[$segmentId])) {
                $result[$segmentId] = [];
            }
            $result[$segmentId][] = $p;
        }

        return $result;
    }

    /**
     * Calcule la progression des prerequis globaux d'une campagne
     *
     * @return array{total: int, faits: int, pourcentage: int}
     */
    public function getProgressionGlobale(Campagne $campagne): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id) as total')
            ->addSelect('SUM(CASE WHEN p.statut = :fait THEN 1 ELSE 0 END) as faits')
            ->where('p.campagne = :campagne')
            ->andWhere('p.segment IS NULL')
            ->setParameter('campagne', $campagne)
            ->setParameter('fait', Prerequis::STATUT_FAIT);

        $result = $qb->getQuery()->getSingleResult();

        $total = (int) $result['total'];
        $faits = (int) $result['faits'];
        $pourcentage = $total > 0 ? (int) round(($faits / $total) * 100) : 0;

        return [
            'total' => $total,
            'faits' => $faits,
            'pourcentage' => $pourcentage,
        ];
    }

    /**
     * Calcule la progression des prerequis d'un segment
     *
     * @return array{total: int, faits: int, pourcentage: int}
     */
    public function getProgressionSegment(Segment $segment): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id) as total')
            ->addSelect('SUM(CASE WHEN p.statut = :fait THEN 1 ELSE 0 END) as faits')
            ->where('p.segment = :segment')
            ->setParameter('segment', $segment)
            ->setParameter('fait', Prerequis::STATUT_FAIT);

        $result = $qb->getQuery()->getSingleResult();

        $total = (int) $result['total'];
        $faits = (int) $result['faits'];
        $pourcentage = $total > 0 ? (int) round(($faits / $total) * 100) : 0;

        return [
            'total' => $total,
            'faits' => $faits,
            'pourcentage' => $pourcentage,
        ];
    }

    /**
     * Calcule la progression de tous les segments d'une campagne
     *
     * @return array<int, array{total: int, faits: int, pourcentage: int}>
     */
    public function getProgressionParSegment(Campagne $campagne): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.segment) as segment_id')
            ->addSelect('COUNT(p.id) as total')
            ->addSelect('SUM(CASE WHEN p.statut = :fait THEN 1 ELSE 0 END) as faits')
            ->where('p.campagne = :campagne')
            ->andWhere('p.segment IS NOT NULL')
            ->setParameter('campagne', $campagne)
            ->setParameter('fait', Prerequis::STATUT_FAIT)
            ->groupBy('p.segment');

        $results = $qb->getQuery()->getResult();

        $progressions = [];
        foreach ($results as $row) {
            $total = (int) $row['total'];
            $faits = (int) $row['faits'];
            $progressions[(int) $row['segment_id']] = [
                'total' => $total,
                'faits' => $faits,
                'pourcentage' => $total > 0 ? (int) round(($faits / $total) * 100) : 0,
            ];
        }

        return $progressions;
    }

    /**
     * Compte les prerequis en retard pour une campagne
     */
    public function countEnRetard(Campagne $campagne): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.campagne = :campagne')
            ->andWhere('p.statut != :fait')
            ->andWhere('p.dateCible IS NOT NULL')
            ->andWhere('p.dateCible < :today')
            ->setParameter('campagne', $campagne)
            ->setParameter('fait', Prerequis::STATUT_FAIT)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve le prochain numero d'ordre pour un nouveau prerequis
     */
    public function getNextOrdre(Campagne $campagne, ?Segment $segment = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('MAX(p.ordre)')
            ->where('p.campagne = :campagne')
            ->setParameter('campagne', $campagne);

        if ($segment === null) {
            $qb->andWhere('p.segment IS NULL');
        } else {
            $qb->andWhere('p.segment = :segment')
                ->setParameter('segment', $segment);
        }

        $maxOrdre = $qb->getQuery()->getSingleScalarResult();

        return ($maxOrdre ?? 0) + 1;
    }

    public function save(Prerequis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Prerequis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
