<?php

namespace App\Repository;

use App\Entity\Campagne;
use App\Entity\Utilisateur;
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
     * RG-112 : Trouve les campagnes visibles par un utilisateur
     * - Campagnes publiques
     * - Campagnes dont l'utilisateur est proprietaire
     * - Campagnes dont l'utilisateur est habilite
     *
     * @return Campagne[]
     */
    public function findVisiblesPar(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.utilisateursHabilites', 'uh')
            ->andWhere('c.visibilite = :publique OR c.proprietaire = :user OR uh = :user')
            ->setParameter('publique', Campagne::VISIBILITE_PUBLIQUE)
            ->setParameter('user', $utilisateur)
            ->orderBy('c.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * RG-112 : Trouve les campagnes visibles groupees par statut
     *
     * @return array<string, Campagne[]>
     */
    public function findVisiblesGroupedByStatut(Utilisateur $utilisateur, bool $isAdmin = false): array
    {
        // Les admins voient tout
        if ($isAdmin) {
            return $this->findAllGroupedByStatut();
        }

        $campagnes = $this->findVisiblesPar($utilisateur);

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

    /**
     * RG-041 : Trouve une campagne par son token de partage
     */
    public function findOneByShareToken(string $token): ?Campagne
    {
        return $this->findOneBy(['shareToken' => $token]);
    }

    /**
     * T-1307 : Trouve les campagnes actives filtrees par statuts
     *
     * @param string[] $statuts
     * @return Campagne[]
     */
    public function findByStatuts(array $statuts): array
    {
        if (empty($statuts)) {
            return $this->findActives();
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('statuts', $statuts)
            ->orderBy('c.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
