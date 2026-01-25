<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Agent;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entite Agent.
 *
 * FINDING-004 : Tests de validation du telephone format E.164.
 */
class AgentTest extends TestCase
{
    // ==========================================
    // Tests setTelephone - Normalisation
    // ==========================================

    public function testSetTelephoneNormalizesFrenchMobile06(): void
    {
        $agent = new Agent();
        $agent->setTelephone('06 12 34 56 78');

        $this->assertEquals('+33612345678', $agent->getTelephone());
    }

    public function testSetTelephoneNormalizesFrenchMobile07(): void
    {
        $agent = new Agent();
        $agent->setTelephone('07 98 76 54 32');

        $this->assertEquals('+33798765432', $agent->getTelephone());
    }

    public function testSetTelephoneNormalizesWithDashes(): void
    {
        $agent = new Agent();
        $agent->setTelephone('06-12-34-56-78');

        $this->assertEquals('+33612345678', $agent->getTelephone());
    }

    public function testSetTelephoneNormalizesWithDots(): void
    {
        $agent = new Agent();
        $agent->setTelephone('06.12.34.56.78');

        $this->assertEquals('+33612345678', $agent->getTelephone());
    }

    public function testSetTelephoneNormalizesWithoutPlus(): void
    {
        $agent = new Agent();
        $agent->setTelephone('33612345678');

        $this->assertEquals('+33612345678', $agent->getTelephone());
    }

    public function testSetTelephoneAcceptsInternationalFormat(): void
    {
        $agent = new Agent();
        $agent->setTelephone('+33612345678');

        $this->assertEquals('+33612345678', $agent->getTelephone());
    }

    public function testSetTelephoneAcceptsOtherCountries(): void
    {
        $agent = new Agent();
        $agent->setTelephone('+1234567890');

        $this->assertEquals('+1234567890', $agent->getTelephone());
    }

    // ==========================================
    // Tests setTelephone - Null et vide
    // ==========================================

    public function testSetTelephoneAcceptsNull(): void
    {
        $agent = new Agent();
        $agent->setTelephone(null);

        $this->assertNull($agent->getTelephone());
    }

    public function testSetTelephoneAcceptsEmptyString(): void
    {
        $agent = new Agent();
        $agent->setTelephone('');

        $this->assertNull($agent->getTelephone());
    }

    public function testSetTelephoneCanBeResetToNull(): void
    {
        $agent = new Agent();
        $agent->setTelephone('+33612345678');
        $this->assertEquals('+33612345678', $agent->getTelephone());

        $agent->setTelephone(null);
        $this->assertNull($agent->getTelephone());
    }

    // ==========================================
    // Tests setTelephone - Rejet invalides
    // ==========================================

    public function testSetTelephoneRejectsTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide');

        $agent = new Agent();
        $agent->setTelephone('12345');
    }

    public function testSetTelephoneRejectsLandline(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide');

        $agent = new Agent();
        $agent->setTelephone('01 23 45 67 89');
    }

    public function testSetTelephoneRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide');

        $agent = new Agent();
        $agent->setTelephone('abc123');
    }

    public function testSetTelephoneRejectsNoCountryCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide');

        $agent = new Agent();
        $agent->setTelephone('612345678'); // Manque indicatif pays
    }

    // ==========================================
    // Tests canReceiveSms
    // ==========================================

    public function testCanReceiveSmsRequiresBothOptInAndTelephone(): void
    {
        $agent = new Agent();

        // Sans telephone ni opt-in
        $this->assertFalse($agent->canReceiveSms());

        // Avec telephone mais sans opt-in
        $agent->setTelephone('+33612345678');
        $this->assertFalse($agent->canReceiveSms());

        // Avec opt-in mais sans telephone
        $agent->setTelephone(null);
        $agent->setSmsOptIn(true);
        $this->assertFalse($agent->canReceiveSms());

        // Avec les deux
        $agent->setTelephone('+33612345678');
        $agent->setSmsOptIn(true);
        $this->assertTrue($agent->canReceiveSms());
    }

    public function testCanReceiveSmsReturnsFalseWhenOptInDisabled(): void
    {
        $agent = new Agent();
        $agent->setTelephone('+33612345678');
        $agent->setSmsOptIn(false);

        $this->assertFalse($agent->canReceiveSms());
    }

    public function testCanReceiveSmsReturnsTrueWhenAllConditionsMet(): void
    {
        $agent = new Agent();
        $agent->setTelephone('+33612345678');
        $agent->setSmsOptIn(true);

        $this->assertTrue($agent->canReceiveSms());
    }

    // ==========================================
    // Tests smsOptIn
    // ==========================================

    public function testSmsOptInDefaultsToFalse(): void
    {
        $agent = new Agent();

        $this->assertFalse($agent->isSmsOptIn());
    }

    public function testSetSmsOptIn(): void
    {
        $agent = new Agent();
        $agent->setSmsOptIn(true);

        $this->assertTrue($agent->isSmsOptIn());

        $agent->setSmsOptIn(false);
        $this->assertFalse($agent->isSmsOptIn());
    }
}
