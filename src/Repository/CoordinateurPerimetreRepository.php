<?php

namespace App\Repository;

use App\Entity\CoordinateurPerimetre;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CoordinateurPerimetre>
 */
class CoordinateurPerimetreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoordinateurPerimetre::class);
    }

    /**
     * RG-114 : Trouve les perimetres d'un coordinateur
     *
     * @return CoordinateurPerimetre[]
     */
    public function findByCoordinateur(Utilisateur $coordinateur): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.coordinateur = :coordinateur')
            ->setParameter('coordinateur', $coordinateur)
            ->orderBy('cp.service', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les services delegues a un coordinateur
     *
     * @return string[]
     */
    public function findServicesForCoordinateur(Utilisateur $coordinateur): array
    {
        $results = $this->createQueryBuilder('cp')
            ->select('cp.service')
            ->andWhere('cp.coordinateur = :coordinateur')
            ->setParameter('coordinateur', $coordinateur)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'service');
    }

    /**
     * Verifie si un coordinateur a acces a un service
     */
    public function hasAccessToService(Utilisateur $coordinateur, string $service): bool
    {
        $count = $this->createQueryBuilder('cp')
            ->select('COUNT(cp.id)')
            ->andWhere('cp.coordinateur = :coordinateur')
            ->andWhere('cp.service = :service')
            ->setParameter('coordinateur', $coordinateur)
            ->setParameter('service', $service)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Verifie si un coordinateur a acces a un service/site
     */
    public function hasAccessToServiceAndSite(Utilisateur $coordinateur, string $service, ?string $site): bool
    {
        $qb = $this->createQueryBuilder('cp')
            ->select('COUNT(cp.id)')
            ->andWhere('cp.coordinateur = :coordinateur')
            ->andWhere('cp.service = :service')
            ->setParameter('coordinateur', $coordinateur)
            ->setParameter('service', $service);

        if ($site) {
            $qb->andWhere('cp.site IS NULL OR cp.site = :site')
               ->setParameter('site', $site);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
