<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service de gestion des utilisateurs.
 *
 * Règles métier implémentées :
 * - RG-001 : Validation mot de passe (8 car min, 1 maj, 1 chiffre, 1 spécial)
 * - RG-002 : Email unique
 * - RG-003 : Gestion des rôles
 */
class UtilisateurService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UtilisateurRepository $utilisateurRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * Crée un nouvel utilisateur.
     *
     * @param string $email
     * @param string $plainPassword
     * @param string $nom
     * @param string $prenom
     * @param array $roles
     * @return Utilisateur
     * @throws \InvalidArgumentException si validation échoue
     */
    public function createUtilisateur(
        string $email,
        string $plainPassword,
        string $nom,
        string $prenom,
        array $roles = [Utilisateur::ROLE_TECHNICIEN],
    ): Utilisateur {
        // Vérifier si l'email existe déjà (RG-002)
        if ($this->utilisateurRepository->findByEmail($email) !== null) {
            throw new \InvalidArgumentException('Un utilisateur existe deja avec cet email.');
        }

        // Valider le mot de passe (RG-001)
        $this->validatePassword($plainPassword);

        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email);
        $utilisateur->setNom($nom);
        $utilisateur->setPrenom($prenom);
        $utilisateur->setRoles($roles);
        $utilisateur->setActif(true);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($utilisateur, $plainPassword);
        $utilisateur->setPassword($hashedPassword);

        // Valider l'entité
        $errors = $this->validator->validate($utilisateur);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }
            throw new \InvalidArgumentException(implode(' ', $messages));
        }

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $utilisateur;
    }

    /**
     * Crée un administrateur.
     */
    public function createAdmin(
        string $email,
        string $plainPassword,
        string $nom,
        string $prenom,
    ): Utilisateur {
        return $this->createUtilisateur(
            $email,
            $plainPassword,
            $nom,
            $prenom,
            [Utilisateur::ROLE_ADMIN],
        );
    }

    /**
     * Crée un gestionnaire (Sophie).
     */
    public function createGestionnaire(
        string $email,
        string $plainPassword,
        string $nom,
        string $prenom,
    ): Utilisateur {
        return $this->createUtilisateur(
            $email,
            $plainPassword,
            $nom,
            $prenom,
            [Utilisateur::ROLE_GESTIONNAIRE],
        );
    }

    /**
     * Crée un technicien (Karim).
     */
    public function createTechnicien(
        string $email,
        string $plainPassword,
        string $nom,
        string $prenom,
    ): Utilisateur {
        return $this->createUtilisateur(
            $email,
            $plainPassword,
            $nom,
            $prenom,
            [Utilisateur::ROLE_TECHNICIEN],
        );
    }

    /**
     * Met à jour le mot de passe d'un utilisateur.
     */
    public function updatePassword(Utilisateur $utilisateur, string $newPlainPassword): void
    {
        $this->validatePassword($newPlainPassword);

        $hashedPassword = $this->passwordHasher->hashPassword($utilisateur, $newPlainPassword);
        $utilisateur->setPassword($hashedPassword);

        $this->entityManager->flush();
    }

    /**
     * Active ou désactive un utilisateur.
     */
    public function setActif(Utilisateur $utilisateur, bool $actif): void
    {
        $utilisateur->setActif($actif);
        $this->entityManager->flush();
    }

    /**
     * Déverrouille manuellement un compte.
     */
    public function unlock(Utilisateur $utilisateur): void
    {
        $utilisateur->resetFailedLoginAttempts();
        $this->entityManager->flush();
    }

    /**
     * Valide le mot de passe selon RG-001.
     *
     * Minimum 8 caractères, 1 majuscule, 1 chiffre, 1 caractère spécial.
     *
     * @throws \InvalidArgumentException si le mot de passe ne respecte pas les règles
     */
    public function validatePassword(string $password): void
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractere special.';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }
}
