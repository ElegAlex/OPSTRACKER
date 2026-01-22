<?php

namespace App\EventSubscriber;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Gestion du verrouillage de compte après échecs de connexion (RG-006).
 *
 * - Après 5 échecs consécutifs → verrouillage 15 minutes
 * - Après succès → reset du compteur
 */
class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UtilisateurRepository $utilisateurRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    /**
     * Connexion réussie : reset du compteur d'échecs.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Utilisateur) {
            return;
        }

        if ($user->getFailedLoginAttempts() > 0 || $user->getLockedUntil() !== null) {
            $user->resetFailedLoginAttempts();
            $this->entityManager->flush();
        }
    }

    /**
     * Échec de connexion : incrémenter le compteur et éventuellement verrouiller.
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $passport = $event->getPassport();

        if ($passport === null) {
            return;
        }

        try {
            $userBadge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);
            $email = $userBadge->getUserIdentifier();
        } catch (\Exception) {
            return;
        }

        $user = $this->utilisateurRepository->findByEmail($email);

        if (!$user instanceof Utilisateur) {
            return;
        }

        // Ne pas incrémenter si déjà verrouillé
        if ($user->isLocked()) {
            return;
        }

        $user->incrementFailedLoginAttempts();

        // Verrouiller si seuil atteint (RG-006: 5 échecs)
        if ($user->shouldBeLocked()) {
            $user->lock();
        }

        $this->entityManager->flush();
    }
}
