<?php

namespace App\Service;

use App\Entity\Operation;
use App\Entity\Utilisateur;
use App\Repository\OperationRepository;
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
        private OperationRepository $operationRepository,
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
     * @param list<string> $roles
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
        $utilisateur->setRoles(array_values($roles));
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
     * Cree un coordinateur (RG-114).
     * Peut positionner des agents sans lien hierarchique.
     */
    public function createCoordinateur(
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
            [Utilisateur::ROLE_COORDINATEUR],
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

    /**
     * T-1003 : Recupere les statistiques d'un utilisateur
     *
     * @return array{
     *     total_operations: int,
     *     operations_par_statut: array<string, int>,
     *     taux_realisation: float,
     *     activite_recente: array<int, mixed>,
     *     derniere_connexion: ?\DateTimeImmutable,
     *     compte_cree_le: ?\DateTimeImmutable
     * }
     */
    public function getStatistiques(Utilisateur $utilisateur): array
    {
        // Statistiques operations seulement pour les techniciens
        $totalOperations = 0;
        $operationsParStatut = [];
        $tauxRealisation = 0.0;
        $activiteRecente = [];

        $userId = $utilisateur->getId();
        if ($utilisateur->isTechnicien() && $userId !== null) {
            $totalOperations = $this->operationRepository->countByTechnicien($userId);
            $operationsParStatut = $this->operationRepository->countByStatutForTechnicien($userId);
            $activiteRecente = $this->operationRepository->findRecentActivityByTechnicien($userId, 5);

            // Calculer le taux de realisation
            $realisees = $operationsParStatut[Operation::STATUT_REALISE] ?? 0;
            if ($totalOperations > 0) {
                $tauxRealisation = round(($realisees / $totalOperations) * 100, 1);
            }
        }

        return [
            'total_operations' => $totalOperations,
            'operations_par_statut' => $operationsParStatut,
            'taux_realisation' => $tauxRealisation,
            'activite_recente' => $activiteRecente,
            'derniere_connexion' => null, // Pas de tracking pour l'instant
            'compte_cree_le' => $utilisateur->getCreatedAt(),
        ];
    }

    /**
     * Met a jour le profil d'un utilisateur (nom, prenom, email)
     * RG-002 : Verifie l'unicite de l'email
     */
    public function updateProfile(
        Utilisateur $utilisateur,
        string $nom,
        string $prenom,
        string $email
    ): void {
        // Verifier si l'email a change et s'il est deja utilise
        if ($utilisateur->getEmail() !== strtolower(trim($email))) {
            $existing = $this->utilisateurRepository->findByEmail($email);
            if ($existing !== null && $existing->getId() !== $utilisateur->getId()) {
                throw new \InvalidArgumentException('Un utilisateur existe deja avec cet email.');
            }
        }

        $utilisateur->setNom($nom);
        $utilisateur->setPrenom($prenom);
        $utilisateur->setEmail($email);

        $errors = $this->validator->validate($utilisateur);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }
            throw new \InvalidArgumentException(implode(' ', $messages));
        }

        $this->entityManager->flush();
    }

    /**
     * Met a jour les roles d'un utilisateur
     * RG-003 : Roles valides uniquement
     * RG-004 : Un admin ne peut pas se retrograder lui-meme (verifie dans le controller)
     *
     * @param list<string> $roles
     */
    public function updateRoles(Utilisateur $utilisateur, array $roles): void
    {
        $validRoles = [
            Utilisateur::ROLE_ADMIN,
            Utilisateur::ROLE_GESTIONNAIRE,
            Utilisateur::ROLE_TECHNICIEN,
            Utilisateur::ROLE_COORDINATEUR,
        ];

        foreach ($roles as $role) {
            if (!in_array($role, $validRoles, true)) {
                throw new \InvalidArgumentException(sprintf('Role invalide : %s', $role));
            }
        }

        $utilisateur->setRoles(array_values($roles));
        $this->entityManager->flush();
    }
}
