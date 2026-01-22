<?php

namespace App\Security;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Security\SecurityProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Fournit l'utilisateur courant au système d'audit.
 *
 * RG-070 : Audit trail complet (qui, quoi, quand)
 */
class AuditSecurityProvider implements SecurityProviderInterface
{
    public function __construct(
        private readonly Security $security
    ) {
    }

    public function __invoke(): array
    {
        $user = $this->security->getUser();

        if ($user === null) {
            return [
                'user_id' => null,
                'username' => 'anonymous',
            ];
        }

        return [
            'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
            'username' => $user->getUserIdentifier(),
        ];
    }
}
