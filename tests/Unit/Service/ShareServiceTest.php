<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Campagne;
use App\Repository\CampagneRepository;
use App\Service\ShareService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ShareService.
 *
 * User Story : US-605 - Partager une URL lecture seule
 * Regle metier : RG-041 - URLs partagees = consultation uniquement
 */
class ShareServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CampagneRepository&MockObject $campagneRepository;
    private ShareService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->campagneRepository = $this->createMock(CampagneRepository::class);

        $this->service = new ShareService(
            $this->entityManager,
            $this->campagneRepository
        );
    }

    /**
     * Test generation d'un token de partage.
     */
    public function testGenerateShareToken(): void
    {
        $campagne = new Campagne();
        $campagne->setNom('Test');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        $this->campagneRepository
            ->method('findOneByShareToken')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $token = $this->service->generateShareToken($campagne);

        $this->assertSame(12, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $token);
        $this->assertSame($token, $campagne->getShareToken());
        $this->assertNotNull($campagne->getShareTokenCreatedAt());
    }

    /**
     * Test generation regenere si token deja existant.
     */
    public function testGenerateShareTokenRetriesOnConflict(): void
    {
        $campagne = new Campagne();
        $campagne->setNom('Test');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        $existingCampagne = new Campagne();

        // Premiere tentative: conflit, deuxieme: OK
        $this->campagneRepository
            ->method('findOneByShareToken')
            ->willReturnOnConsecutiveCalls($existingCampagne, null);

        $token = $this->service->generateShareToken($campagne);

        $this->assertSame(12, strlen($token));
    }

    /**
     * Test revocation du token de partage.
     */
    public function testRevokeShareToken(): void
    {
        $campagne = new Campagne();
        $campagne->setNom('Test');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));
        $campagne->setShareToken('existingToken');
        $campagne->setShareTokenCreatedAt(new \DateTimeImmutable());

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->revokeShareToken($campagne);

        $this->assertNull($campagne->getShareToken());
        $this->assertNull($campagne->getShareTokenCreatedAt());
    }

    /**
     * Test recherche par token.
     */
    public function testFindByShareToken(): void
    {
        $campagne = new Campagne();
        $campagne->setNom('Test');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        $this->campagneRepository
            ->method('findOneByShareToken')
            ->with('abc123XYZ456')
            ->willReturn($campagne);

        $result = $this->service->findByShareToken('abc123XYZ456');

        $this->assertSame($campagne, $result);
    }

    /**
     * Test recherche par token inexistant.
     */
    public function testFindByShareTokenNotFound(): void
    {
        $this->campagneRepository
            ->method('findOneByShareToken')
            ->with('nonexistent')
            ->willReturn(null);

        $result = $this->service->findByShareToken('nonexistent');

        $this->assertNull($result);
    }

    /**
     * Test validation token - format valide.
     */
    public function testIsValidTokenWithValidToken(): void
    {
        $this->assertTrue($this->service->isValidToken('abcDEF123456'));
        $this->assertTrue($this->service->isValidToken('ABCDEFGHIJKL'));
        $this->assertTrue($this->service->isValidToken('123456789012'));
    }

    /**
     * Test validation token - format invalide (trop court).
     */
    public function testIsValidTokenTooShort(): void
    {
        $this->assertFalse($this->service->isValidToken('abc'));
        $this->assertFalse($this->service->isValidToken('12345678901'));
    }

    /**
     * Test validation token - format invalide (trop long).
     */
    public function testIsValidTokenTooLong(): void
    {
        $this->assertFalse($this->service->isValidToken('abcdefghijklm'));
        $this->assertFalse($this->service->isValidToken('1234567890123'));
    }

    /**
     * Test validation token - caracteres invalides.
     */
    public function testIsValidTokenWithInvalidCharacters(): void
    {
        $this->assertFalse($this->service->isValidToken('abc-def-ghij'));
        $this->assertFalse($this->service->isValidToken('abc def ghij'));
        $this->assertFalse($this->service->isValidToken('abc@def#ghij'));
        $this->assertFalse($this->service->isValidToken('abc_def_ghij'));
    }

    /**
     * Test validation token - chaine vide.
     */
    public function testIsValidTokenEmpty(): void
    {
        $this->assertFalse($this->service->isValidToken(''));
    }

    /**
     * Test que le token genere est toujours de longueur 12.
     */
    public function testGeneratedTokenLength(): void
    {
        $campagne = new Campagne();
        $campagne->setNom('Test');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        $this->campagneRepository
            ->method('findOneByShareToken')
            ->willReturn(null);

        // Generer plusieurs tokens pour verifier la constance
        for ($i = 0; $i < 5; $i++) {
            $token = $this->service->generateShareToken($campagne);
            $this->assertSame(12, strlen($token));
        }
    }

    /**
     * Test que le timestamp est defini lors de la generation.
     */
    public function testShareTokenCreatedAtIsSet(): void
    {
        $campagne = new Campagne();
        $campagne->setNom('Test');
        $campagne->setDateDebut(new \DateTimeImmutable());
        $campagne->setDateFin(new \DateTimeImmutable('+1 month'));

        $this->campagneRepository
            ->method('findOneByShareToken')
            ->willReturn(null);

        $before = new \DateTimeImmutable();
        $this->service->generateShareToken($campagne);
        $after = new \DateTimeImmutable();

        $createdAt = $campagne->getShareTokenCreatedAt();
        $this->assertNotNull($createdAt);
        $this->assertGreaterThanOrEqual($before, $createdAt);
        $this->assertLessThanOrEqual($after, $createdAt);
    }
}
