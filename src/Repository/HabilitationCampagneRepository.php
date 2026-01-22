<?php

namespace App\Repository;

use App\Entity\Campagne;
use App\Entity\HabilitationCampagne;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour HabilitationCampagne.
 *
 * RG-115 : Droits granulaires par utilisateur
 *
 * @extends ServiceEntityRepository<HabilitationCampagne>
 */
class HabilitationCampagneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HabilitationCampagne::class);
    }

    /**
     * Trouve l'habilitation d'un utilisateur pour une campagne.
     */
    public function findByCampagneAndUtilisateur(Campagne $campagne, Utilisateur $utilisateur): ?HabilitationCampagne
    {
        return $this->findOneBy([
            'campagne' => $campagne,
            'utilisateur' => $utilisateur,
        ]);
    }

    /**
     * Trouve toutes les habilitations d'une campagne.
     *
     * @return HabilitationCampagne[]
     */
    public function findByCampagne(Campagne $campagne): array
    {
        return $this->createQueryBuilder('h')
            ->join('h.utilisateur', 'u')
            ->where('h.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les campagnes visibles par un utilisateur.
     *
     * @return Campagne[]
     */
    public function findCampagnesVisiblesParUtilisateur(Utilisateur $utilisateur): array
    {
        $qb = $this->createQueryBuilder('h')
            ->select('IDENTITY(h.campagne)')
            ->where('h.utilisateur = :utilisateur')
            ->andWhere('h.peutVoir = true')
            ->setParameter('utilisateur', $utilisateur);

        $ids = array_column($qb->getQuery()->getScalarResult(), '1');

        if (empty($ids)) {
            return [];
        }

        return $this->getEntityManager()
            ->getRepository(Campagne::class)
            ->findBy(['id' => $ids]);
    }

    /**
     * Verifie si un utilisateur a un droit specifique sur une campagne.
     */
    public function hasPermission(Campagne $campagne, Utilisateur $utilisateur, string $droit): bool
    {
        $habilitation = $this->findByCampagneAndUtilisateur($campagne, $utilisateur);

        if ($habilitation === null) {
            return false;
        }

        return match ($droit) {
            HabilitationCampagne::DROIT_VOIR => $habilitation->peutVoir(),
            HabilitationCampagne::DROIT_POSITIONNER => $habilitation->peutPositionner(),
            HabilitationCampagne::DROIT_CONFIGURER => $habilitation->peutConfigurer(),
            HabilitationCampagne::DROIT_EXPORTER => $habilitation->peutExporter(),
            default => false,
        };
    }

    public function save(HabilitationCampagne $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HabilitationCampagne $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
