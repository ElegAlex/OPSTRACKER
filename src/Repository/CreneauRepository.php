<?php

namespace App\Repository;

use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Segment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Creneau>
 */
class CreneauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Creneau::class);
    }

    /**
     * Trouve les creneaux d'une campagne
     *
     * @return Creneau[]
     */
    public function findByCampagne(Campagne $campagne): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('c.date', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les creneaux disponibles (non complets, non verrouilles)
     *
     * @return Creneau[]
     */
    public function findDisponibles(Campagne $campagne, ?Segment $segment = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.reservations', 'r', 'WITH', 'r.statut = :statut')
            ->andWhere('c.campagne = :campagne')
            ->andWhere('c.verrouille = :verrouille')
            ->andWhere('c.date > :today')
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', 'confirmee')
            ->setParameter('verrouille', false)
            ->setParameter('today', new \DateTime())
            ->groupBy('c.id')
            ->having('COUNT(r.id) < c.capacite')
            ->orderBy('c.date', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC');

        if ($segment !== null) {
            $qb->andWhere('c.segment = :segment OR c.segment IS NULL')
               ->setParameter('segment', $segment);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les creneaux a verrouiller automatiquement (J-X)
     *
     * @return Creneau[]
     */
    public function findAVerrouiller(int $joursAvant = 2): array
    {
        $dateLimite = (new \DateTime())->modify("+{$joursAvant} days");

        return $this->createQueryBuilder('c')
            ->andWhere('c.verrouille = :verrouille')
            ->andWhere('c.date <= :dateLimite')
            ->setParameter('verrouille', false)
            ->setParameter('dateLimite', $dateLimite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les creneaux par date
     *
     * @return Creneau[]
     */
    public function findByDate(Campagne $campagne, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.campagne = :campagne')
            ->andWhere('c.date = :date')
            ->setParameter('campagne', $campagne)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les creneaux d'une plage de dates
     *
     * @return Creneau[]
     */
    public function findByDateRange(Campagne $campagne, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.campagne = :campagne')
            ->andWhere('c.date >= :debut')
            ->andWhere('c.date <= :fin')
            ->setParameter('campagne', $campagne)
            ->setParameter('debut', $debut->format('Y-m-d'))
            ->setParameter('fin', $fin->format('Y-m-d'))
            ->orderBy('c.date', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les creneaux par segment
     *
     * @return Creneau[]
     */
    public function findBySegment(Segment $segment): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.segment = :segment')
            ->setParameter('segment', $segment)
            ->orderBy('c.date', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les creneaux par jour pour une campagne
     *
     * @return array<string, int>
     */
    public function countByDateForCampagne(Campagne $campagne): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.date AS jour, COUNT(c.id) AS total')
            ->andWhere('c.campagne = :campagne')
            ->setParameter('campagne', $campagne)
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

    /**
     * Calcule les statistiques de remplissage pour une campagne
     *
     * @return array{total: int, complets: int, disponibles: int, places_totales: int, places_prises: int}
     */
    public function getStatistiquesRemplissage(Campagne $campagne): array
    {
        $creneaux = $this->findByCampagne($campagne);

        $stats = [
            'total' => count($creneaux),
            'complets' => 0,
            'disponibles' => 0,
            'places_totales' => 0,
            'places_prises' => 0,
        ];

        foreach ($creneaux as $creneau) {
            $stats['places_totales'] += $creneau->getCapacite();
            $placesRestantes = $creneau->getPlacesRestantes();
            $stats['places_prises'] += ($creneau->getCapacite() - $placesRestantes);

            if ($creneau->isComplet()) {
                $stats['complets']++;
            } else {
                $stats['disponibles']++;
            }
        }

        return $stats;
    }
}
