<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Operation;
use PHPUnit\Framework\TestCase;

class OperationTest extends TestCase
{
    public function testDureeFormateeReturnsNullWhenNotSet(): void
    {
        $operation = new Operation();

        $this->assertNull($operation->getDureeFormatee());
    }

    public function testDureeFormateeReturnsZeroMinutes(): void
    {
        $operation = new Operation();
        $operation->setDureeInterventionMinutes(0);

        $this->assertSame('0min', $operation->getDureeFormatee());
    }

    public function testDureeFormateeReturnsMinutesOnly(): void
    {
        $operation = new Operation();
        $operation->setDureeInterventionMinutes(30);

        $this->assertSame('30min', $operation->getDureeFormatee());
    }

    public function testDureeFormateeReturnsHoursOnly(): void
    {
        $operation = new Operation();
        $operation->setDureeInterventionMinutes(60);

        $this->assertSame('1h', $operation->getDureeFormatee());

        $operation->setDureeInterventionMinutes(120);

        $this->assertSame('2h', $operation->getDureeFormatee());
    }

    public function testDureeFormateeReturnsHoursAndMinutes(): void
    {
        $operation = new Operation();
        $operation->setDureeInterventionMinutes(90);

        $this->assertSame('1h30', $operation->getDureeFormatee());
    }

    public function testDureeFormateeReturnsHoursAndMinutesWithLeadingZero(): void
    {
        $operation = new Operation();
        $operation->setDureeInterventionMinutes(125); // 2h05

        $this->assertSame('2h05', $operation->getDureeFormatee());
    }

    public function testDureeFormateeReturnsHoursAndMinutesForLongDuration(): void
    {
        $operation = new Operation();
        $operation->setDureeInterventionMinutes(500); // 8h20

        $this->assertSame('8h20', $operation->getDureeFormatee());
    }

    public function testSetDureeInterventionMinutesTracksTimestamp(): void
    {
        $operation = new Operation();
        $before = new \DateTimeImmutable();

        $operation->setDureeInterventionMinutes(60);

        $after = new \DateTimeImmutable();

        $this->assertSame(60, $operation->getDureeInterventionMinutes());
        $this->assertNotNull($operation->getDureeRenseigneeLe());
        $this->assertGreaterThanOrEqual($before, $operation->getDureeRenseigneeLe());
        $this->assertLessThanOrEqual($after, $operation->getDureeRenseigneeLe());
    }
}
