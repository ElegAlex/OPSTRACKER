<?php

namespace App\Repository;

use App\Entity\Segment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Segment>
 */
class SegmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Segment::class);
    }

    /**
     * Trouve les segments d'une campagne tries par ordre
     *
     * @return Segment[]
     */
    public function findByCampagne(int $campagneId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
            ->orderBy('s.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * T-2008 / US-1108 : Trouve un segment par site pour une campagne
     * RG-135 : Association creneau <-> segment optionnelle
     */
    public function findByCampagneAndSite(int $campagneId, ?string $site): ?Segment
    {
        if (!$site) {
            return null;
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.campagne = :campagne')
            ->andWhere('s.nom LIKE :site OR s.nom LIKE :sitePattern')
            ->setParameter('campagne', $campagneId)
            ->setParameter('site', $site)
            ->setParameter('sitePattern', '%' . $site . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
