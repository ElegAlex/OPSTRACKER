<?php

namespace App\Repository;

use App\Entity\Campagne;
use App\Entity\CampagneAgentAutorise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CampagneAgentAutorise>
 */
class CampagneAgentAutoriseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CampagneAgentAutorise::class);
    }

    /**
     * Trouve tous les agents autorises pour une campagne, tries par nom.
     *
     * @return CampagneAgentAutorise[]
     */
    public function findByCampagne(Campagne $campagne): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('a.nomPrenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un agent autorise par son identifiant pour une campagne.
     */
    public function findOneByIdentifiant(Campagne $campagne, string $identifiant): ?CampagneAgentAutorise
    {
        return $this->createQueryBuilder('a')
            ->where('a.campagne = :campagne')
            ->andWhere('a.identifiant = :identifiant')
            ->setParameter('campagne', $campagne)
            ->setParameter('identifiant', $identifiant)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre d'agents autorises pour une campagne.
     */
    public function countByCampagne(Campagne $campagne): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Supprime tous les agents autorises d'une campagne.
     */
    public function deleteAllForCampagne(Campagne $campagne): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->getQuery()
            ->execute();
    }
}
