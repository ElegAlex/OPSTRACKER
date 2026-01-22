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
}
