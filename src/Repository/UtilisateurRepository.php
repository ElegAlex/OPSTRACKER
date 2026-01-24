<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Utilisé pour hasher automatiquement les mots de passe lors de rehash.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve un utilisateur par email (insensible à la casse).
     */
    public function findByEmail(string $email): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les utilisateurs actifs.
     *
     * @return Utilisateur[]
     */
    public function findAllActifs(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les techniciens actifs.
     *
     * @return Utilisateur[]
     */
    public function findTechniciensActifs(): array
    {
        $users = $this->findAllActifs();

        return array_values(array_filter($users, fn(Utilisateur $u) =>
            in_array(Utilisateur::ROLE_TECHNICIEN, $u->getRoles(), true)
        ));
    }

    /**
     * Trouve tous les gestionnaires actifs.
     *
     * @return Utilisateur[]
     */
    public function findGestionnairesActifs(): array
    {
        $users = $this->findAllActifs();

        return array_values(array_filter($users, fn(Utilisateur $u) =>
            in_array(Utilisateur::ROLE_GESTIONNAIRE, $u->getRoles(), true)
        ));
    }

    /**
     * Compte les utilisateurs par rôle.
     *
     * @return array<string, int>
     */
    public function countByRole(): array
    {
        $users = $this->findAll();
        $counts = [
            Utilisateur::ROLE_ADMIN => 0,
            Utilisateur::ROLE_GESTIONNAIRE => 0,
            Utilisateur::ROLE_TECHNICIEN => 0,
        ];

        foreach ($users as $user) {
            foreach ($counts as $role => $count) {
                if (in_array($role, $user->getRoles(), true)) {
                    $counts[$role]++;
                }
            }
        }

        return $counts;
    }

    public function save(Utilisateur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Utilisateur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
