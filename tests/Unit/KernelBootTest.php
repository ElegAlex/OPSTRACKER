<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelBootTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel(['environment' => 'test']);

        $this->assertNotNull(self::$kernel);
        $this->assertSame('test', self::$kernel->getEnvironment());
    }

    public function testContainerHasRequiredServices(): void
    {
        self::bootKernel(['environment' => 'test']);
        $container = self::getContainer();

        $this->assertTrue($container->has('doctrine'));
        $this->assertTrue($container->has('twig'));
    }
}
