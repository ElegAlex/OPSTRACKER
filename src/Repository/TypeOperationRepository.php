<?php

namespace App\Repository;

use App\Entity\TypeOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeOperation>
 */
class TypeOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeOperation::class);
    }

    /**
     * Trouve les types actifs
     *
     * @return TypeOperation[]
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
