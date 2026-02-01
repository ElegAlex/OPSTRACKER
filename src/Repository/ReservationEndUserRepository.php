<?php

namespace App\Repository;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\ReservationEndUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReservationEndUser>
 */
class ReservationEndUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationEndUser::class);
    }

    /**
     * Trouve une reservation par identifiant et operation.
     */
    public function findByIdentifiantAndOperation(string $identifiant, Operation $operation): ?ReservationEndUser
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.identifiant = :identifiant')
            ->andWhere('r.operation = :operation')
            ->setParameter('identifiant', $identifiant)
            ->setParameter('operation', $operation)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les reservations d'un identifiant pour une campagne.
     *
     * @return ReservationEndUser[]
     */
    public function findByIdentifiantAndCampagne(string $identifiant, Campagne $campagne): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.operation', 'o')
            ->andWhere('r.identifiant = :identifiant')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('identifiant', $identifiant)
            ->setParameter('campagne', $campagne)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les reservations pour une campagne.
     */
    public function countByCampagne(Campagne $campagne): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.operation', 'o')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les reservations pour une campagne.
     *
     * @return ReservationEndUser[]
     */
    public function findByCampagne(Campagne $campagne): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.operation', 'o')
            ->andWhere('o.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('r.reserveLe', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les reservations avec opt-in SMS pour rappels.
     *
     * @return ReservationEndUser[]
     */
    public function findWithSmsOptIn(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.smsOptIn = true')
            ->andWhere('r.telephone IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
