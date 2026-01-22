<?php

namespace App\Repository;

use App\Entity\ChecklistTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChecklistTemplate>
 */
class ChecklistTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChecklistTemplate::class);
    }

    /**
     * Trouve les templates actifs
     *
     * @return ChecklistTemplate[]
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

    /**
     * Trouve le template le plus recent par nom
     */
    public function findLatestByNom(string $nom): ?ChecklistTemplate
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.nom = :nom')
            ->setParameter('nom', $nom)
            ->orderBy('t.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
