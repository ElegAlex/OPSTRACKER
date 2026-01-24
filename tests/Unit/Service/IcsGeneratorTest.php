<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Service\IcsGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour IcsGenerator.
 *
 * Regles metier testees :
 * - RG-140 : Email confirmation contient ICS obligatoire (format valide)
 */
class IcsGeneratorTest extends TestCase
{
    private IcsGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new IcsGenerator();
    }

    public function testGenerateReturnsValidIcsFormat(): void
    {
        $reservation = $this->createReservation();

        $ics = $this->generator->generate($reservation);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('VERSION:2.0', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }

    public function testGenerateIncludesCorrectDates(): void
    {
        $reservation = $this->createReservation();

        $ics = $this->generator->generate($reservation);

        // Format: DTSTART:20260215T090000
        $this->assertStringContainsString('DTSTART:20260215T090000', $ics);
        $this->assertStringContainsString('DTEND:20260215T093000', $ics);
    }

    public function testGenerateIncludesCampaignName(): void
    {
        $reservation = $this->createReservation();

        $ics = $this->generator->generate($reservation);

        $this->assertStringContainsString('Campagne Test', $ics);
    }

    public function testGenerateIncludesLocation(): void
    {
        $reservation = $this->createReservation();
        $reservation->getCreneau()->setLieu('Salle A101');

        $ics = $this->generator->generate($reservation);

        $this->assertStringContainsString('LOCATION:Salle A101', $ics);
    }

    public function testGenerateIncludesAlarm(): void
    {
        $reservation = $this->createReservation();

        $ics = $this->generator->generate($reservation);

        // Rappel J-1
        $this->assertStringContainsString('BEGIN:VALARM', $ics);
        $this->assertStringContainsString('TRIGGER:-P1D', $ics);
        $this->assertStringContainsString('ACTION:DISPLAY', $ics);
        $this->assertStringContainsString('END:VALARM', $ics);
    }

    public function testGenerateIncludesHourBeforeAlarm(): void
    {
        $reservation = $this->createReservation();

        $ics = $this->generator->generate($reservation);

        // Rappel 1 heure avant
        $this->assertStringContainsString('TRIGGER:-PT1H', $ics);
    }

    public function testGenerateHasUniqueUid(): void
    {
        $reservation1 = $this->createReservation();
        $reservation2 = $this->createReservation();

        $ics1 = $this->generator->generate($reservation1);
        $ics2 = $this->generator->generate($reservation2);

        // Extraire les UID
        preg_match('/UID:(.+)/', $ics1, $matches1);
        preg_match('/UID:(.+)/', $ics2, $matches2);

        // Les UID doivent etre differents (a cause du uniqid)
        $this->assertNotEquals($matches1[1] ?? '', $matches2[1] ?? '');
    }

    public function testGenerateCancellationReturnsValidFormat(): void
    {
        $reservation = $this->createReservation();

        $ics = $this->generator->generateCancellation($reservation);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('METHOD:CANCEL', $ics);
        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
        $this->assertStringContainsString('ANNULEE', $ics);
    }

    public function testGenerateCancellationIncludesCorrectDates(): void
    {
        $reservation = $this->createReservation();

        $ics = $this->generator->generateCancellation($reservation);

        $this->assertStringContainsString('DTSTART:20260215T090000', $ics);
        $this->assertStringContainsString('DTEND:20260215T093000', $ics);
    }

    public function testGenerateHandlesSpecialCharacters(): void
    {
        $reservation = $this->createReservation();
        $reservation->getCreneau()->setLieu('Salle A,B;C');

        $ics = $this->generator->generate($reservation);

        // Les virgules et points-virgules doivent etre echappes
        $this->assertStringContainsString('LOCATION:Salle A\\,B\\;C', $ics);
    }

    public function testGenerateHandlesNullLocation(): void
    {
        $reservation = $this->createReservation();
        $reservation->getCreneau()->setLieu(null);

        $ics = $this->generator->generate($reservation);

        $this->assertStringContainsString('LOCATION:', $ics);
    }

    public function testGenerateUsesCRLFLineEndings(): void
    {
        $reservation = $this->createReservation();

        $ics = $this->generator->generate($reservation);

        // ICS doit utiliser CRLF comme separateur de lignes
        $this->assertStringContainsString("\r\n", $ics);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createReservation(): Reservation
    {
        $agent = new Agent();
        $agent->setMatricule('TEST001');
        $agent->setEmail('agent@test.com');
        $agent->setNom('Dupont');
        $agent->setPrenom('Jean');

        $campagne = new Campagne();
        $campagne->setNom('Campagne Test');
        $campagne->setDateDebut(new \DateTimeImmutable('2026-02-01'));
        $campagne->setDateFin(new \DateTimeImmutable('2026-03-31'));

        $creneau = new Creneau();
        $creneau->setCampagne($campagne);
        $creneau->setDate(new \DateTime('2026-02-15'));
        $creneau->setHeureDebut(new \DateTime('09:00'));
        $creneau->setHeureFin(new \DateTime('09:30'));
        $creneau->setCapacite(5);
        $creneau->setLieu('Salle A101');

        $reservation = new Reservation();
        $reservation->setAgent($agent);
        $reservation->setCreneau($creneau);
        $reservation->setCampagne($campagne);

        // Simuler un ID
        $reflection = new \ReflectionClass($reservation);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($reservation, 1);

        return $reservation;
    }
}
