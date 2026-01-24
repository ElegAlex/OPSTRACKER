<?php

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * RG-121 : Trouve la reservation d'un agent pour une campagne
     */
    public function findByAgentAndCampagne(Agent $agent, Campagne $campagne): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.agent = :agent')
            ->andWhere('r.campagne = :campagne')
            ->andWhere('r.statut = :statut')
            ->setParameter('agent', $agent)
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', 'confirmee')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les reservations d'un agent
     *
     * @return Reservation[]
     */
    public function findByAgent(Agent $agent): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.agent = :agent')
            ->andWhere('r.statut = :statut')
            ->setParameter('agent', $agent)
            ->setParameter('statut', 'confirmee')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * RG-124 : Trouve les reservations des agents d'un manager pour une campagne
     *
     * @return Reservation[]
     */
    public function findByManagerAndCampagne(Agent $manager, Campagne $campagne): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.agent', 'a')
            ->andWhere('a.manager = :manager')
            ->andWhere('r.campagne = :campagne')
            ->andWhere('r.statut = :statut')
            ->setParameter('manager', $manager)
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', 'confirmee')
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les reservations d'un creneau
     *
     * @return Reservation[]
     */
    public function findByCreneau(Creneau $creneau): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.creneau = :creneau')
            ->andWhere('r.statut = :statut')
            ->setParameter('creneau', $creneau)
            ->setParameter('statut', 'confirmee')
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les reservations d'une campagne
     *
     * @return Reservation[]
     */
    public function findByCampagne(Campagne $campagne): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.campagne = :campagne')
            ->andWhere('r.statut = :statut')
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', 'confirmee')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les reservations pour une date donnee (rappels J-X)
     *
     * @return Reservation[]
     */
    public function findPourRappel(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.creneau', 'c')
            ->andWhere('c.date = :date')
            ->andWhere('r.statut = :statut')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('statut', 'confirmee')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les reservations par type de positionnement pour une campagne
     *
     * @return array<string, int>
     */
    public function countByTypePositionnement(Campagne $campagne): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.typePositionnement, COUNT(r.id) AS total')
            ->andWhere('r.campagne = :campagne')
            ->andWhere('r.statut = :statut')
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', 'confirmee')
            ->groupBy('r.typePositionnement')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['typePositionnement']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Trouve les reservations positionnees par un utilisateur IT
     *
     * @return Reservation[]
     */
    public function findByPositionnePar(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.positionnePar = :utilisateur')
            ->andWhere('r.statut = :statut')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('statut', 'confirmee')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les reservations par jour pour une campagne
     *
     * @return array<string, int>
     */
    public function countByDateForCampagne(Campagne $campagne): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('c.date AS jour, COUNT(r.id) AS total')
            ->innerJoin('r.creneau', 'c')
            ->andWhere('r.campagne = :campagne')
            ->andWhere('r.statut = :statut')
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', 'confirmee')
            ->groupBy('c.date')
            ->orderBy('c.date', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $dateKey = $row['jour']->format('Y-m-d');
            $counts[$dateKey] = (int) $row['total'];
        }

        return $counts;
    }
}
