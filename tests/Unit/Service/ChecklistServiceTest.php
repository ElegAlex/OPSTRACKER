<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\ChecklistInstance;
use App\Entity\ChecklistTemplate;
use App\Entity\Operation;
use App\Entity\Utilisateur;
use App\Repository\ChecklistInstanceRepository;
use App\Repository\ChecklistTemplateRepository;
use App\Service\ChecklistService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ChecklistService.
 *
 * Regles metier testees :
 * - RG-030 : Template = Nom + Version + Etapes ordonnees + Phases optionnelles
 * - RG-031 : Snapshot Pattern - l'instance conserve une copie du template
 * - RG-032 : Phases verrouillables
 * - RG-033 : Persistance progression immediate
 */
class ChecklistServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ChecklistTemplateRepository&MockObject $templateRepository;
    private ChecklistInstanceRepository&MockObject $instanceRepository;
    private ChecklistService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->templateRepository = $this->createMock(ChecklistTemplateRepository::class);
        $this->instanceRepository = $this->createMock(ChecklistInstanceRepository::class);

        $this->service = new ChecklistService(
            $this->entityManager,
            $this->templateRepository,
            $this->instanceRepository,
        );
    }

    // ==========================================
    // Tests RG-030 : Creation de templates
    // ==========================================

    public function testCreerTemplateSuccess(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ChecklistTemplate::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $template = $this->service->creerTemplate(
            'Migration Windows 11',
            'Description du template'
        );

        $this->assertSame('Migration Windows 11', $template->getNom());
        $this->assertSame('Description du template', $template->getDescription());
        $this->assertSame(1, $template->getVersion());
        $this->assertTrue($template->isActif());
    }

    public function testAjouterPhaseToTemplate(): void
    {
        $template = $this->createTemplate();

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->ajouterPhase($template, 'Preparation', true);

        $phases = $template->getPhases();
        $this->assertCount(1, $phases);
        $this->assertSame('Preparation', $phases[0]['nom']);
        $this->assertSame('phase-1', $phases[0]['id']);
        $this->assertTrue($phases[0]['verrouillable']);
    }

    public function testAjouterEtapeToPhase(): void
    {
        $template = $this->createTemplate();
        $template->addPhase('phase-1', 'Preparation', 1, false);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->ajouterEtape(
            $template,
            'phase-1',
            'Verifier le materiel',
            'Description de l\'etape',
            true,
            null
        );

        $phases = $template->getPhases();
        $etapes = $phases[0]['etapes'];

        $this->assertCount(1, $etapes);
        $this->assertSame('Verifier le materiel', $etapes[0]['titre']);
        $this->assertSame('Description de l\'etape', $etapes[0]['description']);
        $this->assertTrue($etapes[0]['obligatoire']);
    }

    public function testAjouterMultipleEtapes(): void
    {
        $template = $this->createTemplate();
        $template->addPhase('phase-1', 'Preparation', 1, false);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $this->service->ajouterEtape($template, 'phase-1', 'Etape 1');
        $this->service->ajouterEtape($template, 'phase-1', 'Etape 2');

        $phases = $template->getPhases();
        $this->assertCount(2, $phases[0]['etapes']);
        $this->assertSame('phase-1-etape-1', $phases[0]['etapes'][0]['id']);
        $this->assertSame('phase-1-etape-2', $phases[0]['etapes'][1]['id']);
    }

    // ==========================================
    // Tests RG-031 : Snapshot Pattern
    // ==========================================

    public function testCreerInstancePourOperationCreatesSnapshot(): void
    {
        $template = $this->createTemplateWithEtapes();
        $operation = $this->createOperation();

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ChecklistInstance::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $instance = $this->service->creerInstancePourOperation($operation, $template);

        $this->assertSame($template, $instance->getTemplate());
        $this->assertSame($template->getVersion(), $instance->getTemplateVersion());
        $this->assertSame($template->getEtapes(), $instance->getSnapshot());
        $this->assertSame($operation, $instance->getOperation());
    }

    public function testCreerInstanceReturnsExistingIfAlreadyExists(): void
    {
        $template = $this->createTemplateWithEtapes();
        $existingInstance = new ChecklistInstance();
        $operation = $this->createOperation();

        // Simuler une instance existante
        $reflection = new \ReflectionClass($operation);
        $property = $reflection->getProperty('checklistInstance');
        $property->setValue($operation, $existingInstance);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $result = $this->service->creerInstancePourOperation($operation, $template);

        $this->assertSame($existingInstance, $result);
    }

    // ==========================================
    // Tests RG-033 : Cochage des etapes
    // ==========================================

    public function testCocherEtapeSuccess(): void
    {
        $instance = $this->createInstanceWithEtapes();
        $utilisateur = $this->createUtilisateur(42);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->cocherEtape($instance, 'phase-1-etape-1', $utilisateur);

        $this->assertTrue($instance->isEtapeCochee('phase-1-etape-1'));
        $progression = $instance->getProgression();
        $this->assertTrue($progression['phase-1-etape-1']['cochee']);
        $this->assertSame(42, $progression['phase-1-etape-1']['utilisateurId']);
    }

    public function testCocherEtapeFailsForInexistantEtape(): void
    {
        $instance = $this->createInstanceWithEtapes();
        $utilisateur = $this->createUtilisateur(1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Etape "inexistant" introuvable');

        $this->service->cocherEtape($instance, 'inexistant', $utilisateur);
    }

    public function testDecocherEtapeSuccess(): void
    {
        $instance = $this->createInstanceWithEtapes();
        $utilisateur = $this->createUtilisateur(1);

        // D'abord cocher
        $this->entityManager->method('flush');
        $this->service->cocherEtape($instance, 'phase-1-etape-1', $utilisateur);

        // Puis decocher
        $this->service->decocherEtape($instance, 'phase-1-etape-1');

        $this->assertFalse($instance->isEtapeCochee('phase-1-etape-1'));
    }

    public function testToggleEtape(): void
    {
        $instance = $this->createInstanceWithEtapes();
        $utilisateur = $this->createUtilisateur(1);

        $this->entityManager->method('flush');

        // Toggle 1 : coche
        $this->service->toggleEtape($instance, 'phase-1-etape-1', $utilisateur);
        $this->assertTrue($instance->isEtapeCochee('phase-1-etape-1'));

        // Toggle 2 : decoche
        $this->service->toggleEtape($instance, 'phase-1-etape-1', $utilisateur);
        $this->assertFalse($instance->isEtapeCochee('phase-1-etape-1'));
    }

    // ==========================================
    // Tests RG-032 : Phases verrouillables
    // ==========================================

    public function testCocherEtapeInLockedPhaseThrows(): void
    {
        // Creer une instance avec phases verrouillables
        $instance = $this->createInstanceWithLockedPhases();
        $utilisateur = $this->createUtilisateur(1);

        // La phase 2 est verrouillee tant que la phase 1 n'est pas complete
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Phase non accessible');

        $this->service->cocherEtape($instance, 'phase-2-etape-1', $utilisateur);
    }

    public function testCocherEtapeInUnlockedPhaseAfterPreviousComplete(): void
    {
        $instance = $this->createInstanceWithLockedPhases();
        $utilisateur = $this->createUtilisateur(1);

        $this->entityManager->method('flush');

        // Completer la phase 1
        $this->service->cocherEtape($instance, 'phase-1-etape-1', $utilisateur);
        $this->service->cocherEtape($instance, 'phase-1-etape-2', $utilisateur);

        // Maintenant on peut acceder a la phase 2
        $this->service->cocherEtape($instance, 'phase-2-etape-1', $utilisateur);

        $this->assertTrue($instance->isEtapeCochee('phase-2-etape-1'));
    }

    // ==========================================
    // Tests Progression
    // ==========================================

    public function testGetProgressionEmpty(): void
    {
        $instance = $this->createInstanceWithEtapes();

        $progression = $this->service->getProgression($instance);

        $this->assertSame(4, $progression['total']);
        $this->assertSame(0, $progression['completed']);
        $this->assertEquals(0.0, $progression['percentage']);
        $this->assertFalse($progression['is_complete']);
    }

    public function testGetProgressionPartial(): void
    {
        $instance = $this->createInstanceWithEtapes();
        $utilisateur = $this->createUtilisateur(1);

        $this->entityManager->method('flush');
        $this->service->cocherEtape($instance, 'phase-1-etape-1', $utilisateur);
        $this->service->cocherEtape($instance, 'phase-1-etape-2', $utilisateur);

        $progression = $this->service->getProgression($instance);

        $this->assertSame(4, $progression['total']);
        $this->assertSame(2, $progression['completed']);
        $this->assertEquals(50.0, $progression['percentage']);
        $this->assertFalse($progression['is_complete']);
    }

    public function testGetProgressionComplete(): void
    {
        $instance = $this->createInstanceWithEtapes();
        $utilisateur = $this->createUtilisateur(1);

        $this->entityManager->method('flush');

        // Cocher toutes les etapes
        $this->service->cocherEtape($instance, 'phase-1-etape-1', $utilisateur);
        $this->service->cocherEtape($instance, 'phase-1-etape-2', $utilisateur);
        $this->service->cocherEtape($instance, 'phase-2-etape-1', $utilisateur);
        $this->service->cocherEtape($instance, 'phase-2-etape-2', $utilisateur);

        $progression = $this->service->getProgression($instance);

        $this->assertSame(4, $progression['total']);
        $this->assertSame(4, $progression['completed']);
        $this->assertEquals(100.0, $progression['percentage']);
        $this->assertTrue($progression['is_complete']);
    }

    public function testGetProgressionWithPhaseStats(): void
    {
        $instance = $this->createInstanceWithEtapes();
        $utilisateur = $this->createUtilisateur(1);

        $this->entityManager->method('flush');
        $this->service->cocherEtape($instance, 'phase-1-etape-1', $utilisateur);

        $progression = $this->service->getProgression($instance);

        $this->assertArrayHasKey('phases', $progression);
        $this->assertArrayHasKey('phase-1', $progression['phases']);
        $this->assertSame('Preparation', $progression['phases']['phase-1']['nom']);
        $this->assertSame(2, $progression['phases']['phase-1']['total']);
        $this->assertSame(1, $progression['phases']['phase-1']['completed']);
        $this->assertFalse($progression['phases']['phase-1']['is_complete']);
    }

    // ==========================================
    // Tests Templates repository
    // ==========================================

    public function testGetTemplatesActifs(): void
    {
        $templates = [
            $this->createTemplate(),
            $this->createTemplate(),
        ];

        $this->templateRepository
            ->method('findActifs')
            ->willReturn($templates);

        $result = $this->service->getTemplatesActifs();

        $this->assertCount(2, $result);
    }

    public function testToggleTemplateActif(): void
    {
        $template = $this->createTemplate();
        $this->assertTrue($template->isActif());

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->toggleTemplateActif($template);

        $this->assertFalse($template->isActif());
    }

    // ==========================================
    // Tests Template Demo
    // ==========================================

    public function testCreerTemplateDemo(): void
    {
        $this->entityManager
            ->method('persist');
        $this->entityManager
            ->method('flush');

        $template = $this->service->creerTemplateDemo();

        $this->assertSame('Migration Windows 11', $template->getNom());

        $phases = $template->getPhases();
        $this->assertCount(4, $phases);
        $this->assertSame('Preparation', $phases[0]['nom']);
        $this->assertSame('Sauvegarde', $phases[1]['nom']);
        $this->assertSame('Migration', $phases[2]['nom']);
        $this->assertSame('Verification', $phases[3]['nom']);

        $this->assertSame(8, $template->getNombreEtapes());
    }

    // ==========================================
    // Helper methods
    // ==========================================

    private function createTemplate(): ChecklistTemplate
    {
        $template = new ChecklistTemplate();
        $template->setNom('Test Template');
        $template->setVersion(1);
        $template->setActif(true);

        return $template;
    }

    private function createTemplateWithEtapes(): ChecklistTemplate
    {
        $template = $this->createTemplate();

        $template->addPhase('phase-1', 'Preparation', 1, false);
        $template->addEtapeToPhase('phase-1', 'phase-1-etape-1', 'Etape 1.1');
        $template->addEtapeToPhase('phase-1', 'phase-1-etape-2', 'Etape 1.2');

        $template->addPhase('phase-2', 'Execution', 2, false);
        $template->addEtapeToPhase('phase-2', 'phase-2-etape-1', 'Etape 2.1');
        $template->addEtapeToPhase('phase-2', 'phase-2-etape-2', 'Etape 2.2');

        return $template;
    }

    private function createInstanceWithEtapes(): ChecklistInstance
    {
        $template = $this->createTemplateWithEtapes();
        $instance = new ChecklistInstance();
        $instance->createSnapshotFromTemplate($template);

        return $instance;
    }

    private function createInstanceWithLockedPhases(): ChecklistInstance
    {
        $template = new ChecklistTemplate();
        $template->setNom('Template avec phases verrouillees');
        $template->setVersion(1);

        // Phase 1 verrouillable
        $template->addPhase('phase-1', 'Phase 1', 1, true);
        $template->addEtapeToPhase('phase-1', 'phase-1-etape-1', 'Etape 1.1', null, 1, true);
        $template->addEtapeToPhase('phase-1', 'phase-1-etape-2', 'Etape 1.2', null, 2, true);

        // Phase 2 (bloquee par phase 1)
        $template->addPhase('phase-2', 'Phase 2', 2, false);
        $template->addEtapeToPhase('phase-2', 'phase-2-etape-1', 'Etape 2.1', null, 1, true);

        $instance = new ChecklistInstance();
        $instance->createSnapshotFromTemplate($template);

        return $instance;
    }

    private function createOperation(): Operation
    {
        // RG-015 : matricule et nom sont dans donneesPersonnalisees
        $operation = new Operation();
        $operation->setDonneesPersonnalisees([
            'matricule' => 'MAT-' . uniqid(),
            'nom' => 'Operation test',
        ]);

        return $operation;
    }

    private function createUtilisateur(int $id): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail('user-' . $id . '@test.fr');
        $utilisateur->setNom('Test');
        $utilisateur->setPrenom('User');
        $utilisateur->setPassword('password');

        $reflection = new \ReflectionClass($utilisateur);
        $property = $reflection->getProperty('id');
        $property->setValue($utilisateur, $id);

        return $utilisateur;
    }
}
