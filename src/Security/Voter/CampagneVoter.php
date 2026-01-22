<?php

namespace App\Security\Voter;

use App\Entity\Campagne;
use App\Entity\HabilitationCampagne;
use App\Entity\Utilisateur;
use App\Repository\HabilitationCampagneRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour les permissions sur les campagnes.
 *
 * RG-115 : Habilitations granulaires par campagne
 * RG-112 : Visibilite campagne
 */
class CampagneVoter extends Voter
{
    // Permissions disponibles
    public const VOIR = 'CAMPAGNE_VOIR';
    public const POSITIONNER = 'CAMPAGNE_POSITIONNER';
    public const CONFIGURER = 'CAMPAGNE_CONFIGURER';
    public const EXPORTER = 'CAMPAGNE_EXPORTER';
    public const EDITER = 'CAMPAGNE_EDITER';

    public function __construct(
        private readonly HabilitationCampagneRepository $habilitationRepository
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VOIR,
            self::POSITIONNER,
            self::CONFIGURER,
            self::EXPORTER,
            self::EDITER,
        ], true) && $subject instanceof Campagne;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Utilisateur) {
            return false;
        }

        /** @var Campagne $campagne */
        $campagne = $subject;

        // Admin a tous les droits
        if ($user->isAdmin()) {
            return true;
        }

        // Proprietaire a tous les droits
        if ($campagne->getProprietaire() === $user) {
            return true;
        }

        // Gestionnaire peut voir les campagnes publiques
        if ($user->isGestionnaire() && $campagne->isPublique()) {
            if ($attribute === self::VOIR) {
                return true;
            }
        }

        // Verifier les habilitations specifiques (RG-115)
        $habilitation = $this->habilitationRepository->findByCampagneAndUtilisateur($campagne, $user);

        if ($habilitation === null) {
            // Verifier si l'utilisateur est dans la liste des habilites (ancienne methode)
            if ($campagne->getUtilisateursHabilites()->contains($user)) {
                // Droits par defaut pour les anciens habilites : voir seulement
                return $attribute === self::VOIR;
            }

            return false;
        }

        return match ($attribute) {
            self::VOIR => $habilitation->peutVoir(),
            self::POSITIONNER => $habilitation->peutPositionner(),
            self::CONFIGURER => $habilitation->peutConfigurer(),
            self::EXPORTER => $habilitation->peutExporter(),
            self::EDITER => $habilitation->peutConfigurer(),
            default => false,
        };
    }
}
