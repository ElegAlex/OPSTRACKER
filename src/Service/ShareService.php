<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Repository\CampagneRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour la gestion des liens de partage de campagne.
 *
 * User Story : US-605 - Partager une URL lecture seule
 * Regle metier : RG-041 - URLs partagees = consultation uniquement
 */
class ShareService
{
    private const TOKEN_LENGTH = 12;
    private const TOKEN_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CampagneRepository $campagneRepository,
    ) {
    }

    /**
     * Genere un token de partage unique pour une campagne
     */
    public function generateShareToken(Campagne $campagne): string
    {
        // Generer un token unique
        do {
            $token = $this->generateRandomToken();
        } while ($this->campagneRepository->findOneByShareToken($token) !== null);

        $campagne->setShareToken($token);
        $campagne->setShareTokenCreatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $token;
    }

    /**
     * Revoque le lien de partage d'une campagne
     */
    public function revokeShareToken(Campagne $campagne): void
    {
        $campagne->setShareToken(null);
        $campagne->setShareTokenCreatedAt(null);

        $this->em->flush();
    }

    /**
     * Trouve une campagne par son token de partage
     */
    public function findByShareToken(string $token): ?Campagne
    {
        return $this->campagneRepository->findOneByShareToken($token);
    }

    /**
     * Genere un token aleatoire
     */
    private function generateRandomToken(): string
    {
        $token = '';
        $maxIndex = strlen(self::TOKEN_CHARS) - 1;

        for ($i = 0; $i < self::TOKEN_LENGTH; $i++) {
            $token .= self::TOKEN_CHARS[random_int(0, $maxIndex)];
        }

        return $token;
    }

    /**
     * Verifie si un token est valide (format correct)
     */
    public function isValidToken(string $token): bool
    {
        return strlen($token) === self::TOKEN_LENGTH
            && preg_match('/^[a-zA-Z0-9]+$/', $token);
    }
}
