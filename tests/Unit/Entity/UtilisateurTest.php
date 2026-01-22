<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class UtilisateurTest extends TestCase
{
    private function createUtilisateur(): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail('test@example.com');
        $utilisateur->setNom('Dupont');
        $utilisateur->setPrenom('Jean');
        $utilisateur->setPassword('hashed_password');
        $utilisateur->setRoles([Utilisateur::ROLE_TECHNICIEN]);

        return $utilisateur;
    }

    public function testGettersAndSetters(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertSame('test@example.com', $utilisateur->getEmail());
        $this->assertSame('Dupont', $utilisateur->getNom());
        $this->assertSame('Jean', $utilisateur->getPrenom());
        $this->assertSame('hashed_password', $utilisateur->getPassword());
        $this->assertTrue($utilisateur->isActif());
    }

    public function testEmailIsLowercasedAndTrimmed(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail('  Test@Example.COM  ');

        $this->assertSame('test@example.com', $utilisateur->getEmail());
    }

    public function testGetNomComplet(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertSame('Jean Dupont', $utilisateur->getNomComplet());
    }

    public function testGetUserIdentifier(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertSame('test@example.com', $utilisateur->getUserIdentifier());
    }

    public function testRolesAlwaysIncludesRoleUser(): void
    {
        $utilisateur = $this->createUtilisateur();

        $roles = $utilisateur->getRoles();

        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains(Utilisateur::ROLE_TECHNICIEN, $roles);
    }

    public function testHasRole(): void
    {
        $utilisateur = $this->createUtilisateur();
        $utilisateur->setRoles([Utilisateur::ROLE_ADMIN]);

        $this->assertTrue($utilisateur->hasRole(Utilisateur::ROLE_ADMIN));
        $this->assertTrue($utilisateur->hasRole('ROLE_USER'));
        $this->assertFalse($utilisateur->hasRole(Utilisateur::ROLE_GESTIONNAIRE));
    }

    public function testIsAdmin(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertFalse($utilisateur->isAdmin());

        $utilisateur->setRoles([Utilisateur::ROLE_ADMIN]);

        $this->assertTrue($utilisateur->isAdmin());
    }

    public function testIsGestionnaire(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertFalse($utilisateur->isGestionnaire());

        $utilisateur->setRoles([Utilisateur::ROLE_GESTIONNAIRE]);

        $this->assertTrue($utilisateur->isGestionnaire());
    }

    public function testIsTechnicien(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertTrue($utilisateur->isTechnicien());

        $utilisateur->setRoles([Utilisateur::ROLE_ADMIN]);

        $this->assertFalse($utilisateur->isTechnicien());
    }

    public function testFailedLoginAttemptsIncrementAndReset(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertSame(0, $utilisateur->getFailedLoginAttempts());

        $utilisateur->incrementFailedLoginAttempts();
        $utilisateur->incrementFailedLoginAttempts();

        $this->assertSame(2, $utilisateur->getFailedLoginAttempts());

        $utilisateur->resetFailedLoginAttempts();

        $this->assertSame(0, $utilisateur->getFailedLoginAttempts());
        $this->assertNull($utilisateur->getLockedUntil());
    }

    public function testShouldBeLockedAfterMaxAttempts(): void
    {
        $utilisateur = $this->createUtilisateur();

        for ($i = 0; $i < Utilisateur::MAX_FAILED_ATTEMPTS - 1; $i++) {
            $utilisateur->incrementFailedLoginAttempts();
            $this->assertFalse($utilisateur->shouldBeLocked());
        }

        $utilisateur->incrementFailedLoginAttempts();
        $this->assertTrue($utilisateur->shouldBeLocked());
    }

    public function testLock(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertFalse($utilisateur->isLocked());
        $this->assertNull($utilisateur->getLockedUntil());

        $utilisateur->lock();

        $this->assertTrue($utilisateur->isLocked());
        $this->assertNotNull($utilisateur->getLockedUntil());
        $this->assertGreaterThan(new \DateTimeImmutable(), $utilisateur->getLockedUntil());
    }

    public function testIsLockedReturnsFalseWhenLockExpired(): void
    {
        $utilisateur = $this->createUtilisateur();

        // Simuler un verrouillage expire
        $utilisateur->setLockedUntil(new \DateTimeImmutable('-1 minute'));

        $this->assertFalse($utilisateur->isLocked());
    }

    public function testToString(): void
    {
        $utilisateur = $this->createUtilisateur();

        $this->assertSame('Jean Dupont', (string) $utilisateur);
    }

    public function testActifDefaultsToTrue(): void
    {
        $utilisateur = new Utilisateur();

        $this->assertTrue($utilisateur->isActif());
    }

    public function testSetActif(): void
    {
        $utilisateur = $this->createUtilisateur();

        $utilisateur->setActif(false);
        $this->assertFalse($utilisateur->isActif());

        $utilisateur->setActif(true);
        $this->assertTrue($utilisateur->isActif());
    }

    public function testMaxFailedAttemptsConstant(): void
    {
        $this->assertSame(5, Utilisateur::MAX_FAILED_ATTEMPTS);
    }

    public function testLockoutDurationConstant(): void
    {
        $this->assertSame(15, Utilisateur::LOCKOUT_DURATION_MINUTES);
    }
}
