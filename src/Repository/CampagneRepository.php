<?php

namespace App\Repository;

use App\Entity\Campagne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Campagne>
 */
class CampagneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Campagne::class);
    }

    /**
     * Trouve les campagnes par statut
     *
     * @return Campagne[]
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('c.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les campagnes actives (non archivees)
     *
     * @return Campagne[]
     */
    public function findActives(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut != :archivee')
            ->setParameter('archivee', Campagne::STATUT_ARCHIVEE)
            ->orderBy('c.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les campagnes groupees par statut (pour le portfolio)
     *
     * @return array<string, Campagne[]>
     */
    public function findAllGroupedByStatut(): array
    {
        $campagnes = $this->findBy([], ['dateDebut' => 'ASC']);

        $grouped = [];
        foreach (Campagne::STATUTS as $statut => $label) {
            $grouped[$statut] = [];
        }

        foreach ($campagnes as $campagne) {
            $grouped[$campagne->getStatut()][] = $campagne;
        }

        return $grouped;
    }

    /**
     * Trouve les campagnes d'un proprietaire
     *
     * @return Campagne[]
     */
    public function findByProprietaire(int $proprietaireId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.proprietaire = :proprietaire')
            ->setParameter('proprietaire', $proprietaireId)
            ->orderBy('c.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
