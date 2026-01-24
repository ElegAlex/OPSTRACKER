<?php

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\Notification;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Trouve les notifications en attente d'envoi
     *
     * @return Notification[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.statut = :statut')
            ->setParameter('statut', Notification::STATUT_PENDING)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notifications d'un agent
     *
     * @return Notification[]
     */
    public function findByAgent(Agent $agent): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.agent = :agent')
            ->setParameter('agent', $agent)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notifications d'une reservation
     *
     * @return Notification[]
     */
    public function findByReservation(Reservation $reservation): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.reservation = :reservation')
            ->setParameter('reservation', $reservation)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notifications par type
     *
     * @return Notification[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notifications echouees
     *
     * @return Notification[]
     */
    public function findFailed(): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.statut = :statut')
            ->setParameter('statut', Notification::STATUT_FAILED)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les notifications par statut
     *
     * @return array<string, int>
     */
    public function countByStatut(): array
    {
        $results = $this->createQueryBuilder('n')
            ->select('n.statut, COUNT(n.id) AS total')
            ->groupBy('n.statut')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['statut']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Trouve les notifications recentes (derniers X jours)
     *
     * @return Notification[]
     */
    public function findRecentes(int $jours = 7): array
    {
        $dateLimite = (new \DateTime())->modify("-{$jours} days");

        return $this->createQueryBuilder('n')
            ->andWhere('n.createdAt >= :dateLimite')
            ->setParameter('dateLimite', $dateLimite)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Verifie si un rappel a deja ete envoye pour une reservation
     */
    public function hasRappelEnvoye(Reservation $reservation): bool
    {
        $count = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.reservation = :reservation')
            ->andWhere('n.type = :type')
            ->andWhere('n.statut = :statut')
            ->setParameter('reservation', $reservation)
            ->setParameter('type', Notification::TYPE_RAPPEL)
            ->setParameter('statut', Notification::STATUT_SENT)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
