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
}
