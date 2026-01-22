<?php

namespace App\Security\Voter;

use App\Entity\Operation;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour controler l'acces aux operations.
 *
 * Regles :
 * - Un technicien ne peut voir/modifier que ses operations assignees
 * - Un gestionnaire peut voir/modifier toutes les operations de ses campagnes
 * - Un admin peut tout faire
 */
class OperationVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Operation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Utilisateur) {
            return false;
        }

        /** @var Operation $operation */
        $operation = $subject;

        // Les admins ont tous les droits
        if ($user->isAdmin()) {
            return true;
        }

        // Les gestionnaires ont acces a toutes les operations
        if ($user->isGestionnaire()) {
            return true;
        }

        // Les techniciens ne peuvent acceder qu'a leurs operations assignees
        if ($user->isTechnicien()) {
            return $operation->getTechnicienAssigne() === $user;
        }

        return false;
    }
}
