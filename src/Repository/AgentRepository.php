<?php

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\Campagne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agent>
 */
class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    /**
     * Trouve les agents actifs
     *
     * @return Agent[]
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * RG-124 : Trouve les agents par service
     *
     * @return Agent[]
     */
    public function findByService(string $service): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.service = :service')
            ->andWhere('a.actif = :actif')
            ->setParameter('service', $service)
            ->setParameter('actif', true)
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les agents par site
     *
     * @return Agent[]
     */
    public function findBySite(string $site): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.site = :site')
            ->andWhere('a.actif = :actif')
            ->setParameter('site', $site)
            ->setParameter('actif', true)
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * RG-124 : Trouve les agents d'un manager (meme service)
     *
     * @return Agent[]
     */
    public function findByManager(Agent $manager): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.manager = :manager')
            ->andWhere('a.actif = :actif')
            ->setParameter('manager', $manager)
            ->setParameter('actif', true)
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les agents non positionnes pour une campagne
     *
     * @return Agent[]
     */
    public function findNonPositionnesPourCampagne(Campagne $campagne): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.reservations', 'r', 'WITH', 'r.campagne = :campagne AND r.statut = :statut')
            ->andWhere('a.actif = :actif')
            ->andWhere('r.id IS NULL')
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', 'confirmee')
            ->setParameter('actif', true)
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un agent par son matricule
     */
    public function findOneByMatricule(string $matricule): ?Agent
    {
        return $this->findOneBy(['matricule' => strtoupper(trim($matricule))]);
    }

    /**
     * Trouve un agent par son token de reservation
     */
    public function findOneByBookingToken(string $token): ?Agent
    {
        return $this->findOneBy(['bookingToken' => $token, 'actif' => true]);
    }

    /**
     * Trouve un agent par son email
     */
    public function findOneByEmail(string $email): ?Agent
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    /**
     * Retourne la liste des services distincts
     *
     * @return string[]
     */
    public function findDistinctServices(): array
    {
        $results = $this->createQueryBuilder('a')
            ->select('DISTINCT a.service')
            ->andWhere('a.service IS NOT NULL')
            ->andWhere('a.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('a.service', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'service');
    }

    /**
     * Retourne la liste des sites distincts
     *
     * @return string[]
     */
    public function findDistinctSites(): array
    {
        $results = $this->createQueryBuilder('a')
            ->select('DISTINCT a.site')
            ->andWhere('a.site IS NOT NULL')
            ->andWhere('a.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('a.site', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'site');
    }

    /**
     * Trouve les agents qui sont managers (ont des subordonnes)
     *
     * @return Agent[]
     */
    public function findManagers(): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('App\Entity\Agent', 'sub', 'WITH', 'sub.manager = a')
            ->andWhere('a.actif = :actif')
            ->setParameter('actif', true)
            ->groupBy('a.id')
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * T-2004 / US-1011 : Trouve un agent par email ou matricule
     * RG-128 : Auth carte agent preferee, fallback AD/email
     */
    public function findByEmailOrMatricule(string $identifier): ?Agent
    {
        $identifier = trim($identifier);

        // Essayer par email d'abord
        $agent = $this->findOneByEmail($identifier);
        if ($agent) {
            return $agent;
        }

        // Sinon essayer par matricule
        return $this->findOneByMatricule($identifier);
    }
}
