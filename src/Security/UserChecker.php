<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérification de l'état du compte utilisateur avant et après authentification.
 *
 * Implémente :
 * - Vérification compte actif
 * - Vérification verrouillage RG-006
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Vérifie les conditions AVANT la validation du mot de passe.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        // Vérifier si le compte est verrouillé (RG-006)
        if ($user->isLocked()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est temporairement verrouille suite a plusieurs tentatives de connexion echouees. Reessayez dans quelques minutes.'
            );
        }
    }

    /**
     * Vérifie les conditions APRÈS la validation du mot de passe.
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        // Vérifier si le compte est actif
        if (!$user->isActif()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a ete desactive. Contactez votre administrateur.'
            );
        }
    }
}
