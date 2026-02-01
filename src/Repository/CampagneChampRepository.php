<?php

namespace App\Repository;

use App\Entity\CampagneChamp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CampagneChamp>
 */
class CampagneChampRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CampagneChamp::class);
    }

    /**
     * Trouve les champs d'une campagne tries par ordre
     *
     * @return CampagneChamp[]
     */
    public function findByCampagne(int $campagneId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
