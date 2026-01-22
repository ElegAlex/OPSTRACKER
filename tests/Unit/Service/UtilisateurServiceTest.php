<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Utilisateur;
use App\Repository\OperationRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UtilisateurService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UtilisateurServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UtilisateurRepository&MockObject $utilisateurRepository;
    private OperationRepository&MockObject $operationRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private ValidatorInterface&MockObject $validator;
    private UtilisateurService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->utilisateurRepository = $this->createMock(UtilisateurRepository::class);
        $this->operationRepository = $this->createMock(OperationRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->service = new UtilisateurService(
            $this->entityManager,
            $this->utilisateurRepository,
            $this->operationRepository,
            $this->passwordHasher,
            $this->validator,
        );
    }

    public function testValidatePasswordWithValidPassword(): void
    {
        // Ne doit pas lever d'exception
        $this->service->validatePassword('Password1!');

        $this->assertTrue(true);
    }

    public function testValidatePasswordTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('au moins 8 caracteres');

        $this->service->validatePassword('Pass1!');
    }

    public function testValidatePasswordNoUppercase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('majuscule');

        $this->service->validatePassword('password1!');
    }

    public function testValidatePasswordNoDigit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('chiffre');

        $this->service->validatePassword('Password!');
    }

    public function testValidatePasswordNoSpecialChar(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('caractere special');

        $this->service->validatePassword('Password1');
    }

    public function testCreateUtilisateurWithDuplicateEmail(): void
    {
        $existingUser = new Utilisateur();
        $this->utilisateurRepository
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('existe deja');

        $this->service->createUtilisateur(
            'existing@example.com',
            'Password1!',
            'Dupont',
            'Jean',
        );
    }

    public function testCreateUtilisateurSuccess(): void
    {
        $this->utilisateurRepository
            ->method('findByEmail')
            ->willReturn(null);

        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Utilisateur::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $utilisateur = $this->service->createUtilisateur(
            'new@example.com',
            'Password1!',
            'Dupont',
            'Jean',
            [Utilisateur::ROLE_TECHNICIEN],
        );

        $this->assertSame('new@example.com', $utilisateur->getEmail());
        $this->assertSame('Dupont', $utilisateur->getNom());
        $this->assertSame('Jean', $utilisateur->getPrenom());
        $this->assertSame('hashed_password', $utilisateur->getPassword());
        $this->assertTrue($utilisateur->isActif());
    }

    public function testCreateAdmin(): void
    {
        $this->utilisateurRepository
            ->method('findByEmail')
            ->willReturn(null);

        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        $utilisateur = $this->service->createAdmin(
            'admin@example.com',
            'Password1!',
            'Admin',
            'Super',
        );

        $this->assertTrue($utilisateur->isAdmin());
    }

    public function testCreateGestionnaire(): void
    {
        $this->utilisateurRepository
            ->method('findByEmail')
            ->willReturn(null);

        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        $utilisateur = $this->service->createGestionnaire(
            'gestionnaire@example.com',
            'Password1!',
            'Martin',
            'Sophie',
        );

        $this->assertTrue($utilisateur->isGestionnaire());
    }

    public function testCreateTechnicien(): void
    {
        $this->utilisateurRepository
            ->method('findByEmail')
            ->willReturn(null);

        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        $utilisateur = $this->service->createTechnicien(
            'technicien@example.com',
            'Password1!',
            'Ben Ali',
            'Karim',
        );

        $this->assertTrue($utilisateur->isTechnicien());
    }

    public function testUnlock(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setFailedLoginAttempts(5);
        $utilisateur->lock();

        $this->assertTrue($utilisateur->isLocked());

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->unlock($utilisateur);

        $this->assertSame(0, $utilisateur->getFailedLoginAttempts());
        $this->assertNull($utilisateur->getLockedUntil());
        $this->assertFalse($utilisateur->isLocked());
    }

    public function testSetActif(): void
    {
        $utilisateur = new Utilisateur();

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $this->service->setActif($utilisateur, false);
        $this->assertFalse($utilisateur->isActif());

        $this->service->setActif($utilisateur, true);
        $this->assertTrue($utilisateur->isActif());
    }

    public function testUpdatePassword(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setPassword('old_hash');

        $this->passwordHasher
            ->method('hashPassword')
            ->with($utilisateur, 'NewPassword1!')
            ->willReturn('new_hash');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->updatePassword($utilisateur, 'NewPassword1!');

        $this->assertSame('new_hash', $utilisateur->getPassword());
    }

    public function testUpdatePasswordWithInvalidPasswordThrows(): void
    {
        $utilisateur = new Utilisateur();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->updatePassword($utilisateur, 'weak');
    }
}
