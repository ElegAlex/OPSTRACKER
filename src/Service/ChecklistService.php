<?php

namespace App\Service;

use App\Entity\ChecklistInstance;
use App\Entity\ChecklistTemplate;
use App\Entity\Operation;
use App\Entity\Utilisateur;
use App\Repository\ChecklistInstanceRepository;
use App\Repository\ChecklistTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service metier pour la gestion des checklists.
 *
 * Regles metier implementees :
 * - RG-030 : Template = Nom + Version + Etapes ordonnees + Phases optionnelles
 * - RG-031 : Snapshot Pattern - l'instance conserve une copie du template
 * - RG-032 : Phases verrouillables (phase suivante accessible si precedente complete)
 * - RG-033 : Persistance progression - chaque coche est sauvegardee immediatement
 */
class ChecklistService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChecklistTemplateRepository $templateRepository,
        private readonly ChecklistInstanceRepository $instanceRepository,
    ) {
    }

    /**
     * Cree un nouveau template de checklist.
     * RG-030 : Template = Nom + Version + Etapes ordonnees + Phases optionnelles
     */
    public function creerTemplate(
        string $nom,
        ?string $description = null,
        array $etapes = ['phases' => []]
    ): ChecklistTemplate {
        $template = new ChecklistTemplate();
        $template->setNom($nom);
        $template->setDescription($description);
        $template->setEtapes($etapes);
        $template->setVersion(1);
        $template->setActif(true);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        return $template;
    }

    /**
     * Ajoute une phase a un template.
     */
    public function ajouterPhase(
        ChecklistTemplate $template,
        string $nom,
        bool $verrouillable = false
    ): ChecklistTemplate {
        $phases = $template->getPhases();
        $ordre = count($phases) + 1;
        $id = 'phase-' . $ordre;

        $template->addPhase($id, $nom, $ordre, $verrouillable);
        $this->entityManager->flush();

        return $template;
    }

    /**
     * Ajoute une etape a une phase.
     */
    public function ajouterEtape(
        ChecklistTemplate $template,
        string $phaseId,
        string $titre,
        ?string $description = null,
        bool $obligatoire = true,
        ?int $documentId = null
    ): ChecklistTemplate {
        // Trouver l'ordre de la prochaine etape dans cette phase
        $phases = $template->getPhases();
        $ordre = 1;
        foreach ($phases as $phase) {
            if ($phase['id'] === $phaseId) {
                $ordre = count($phase['etapes'] ?? []) + 1;
                break;
            }
        }

        $etapeId = $phaseId . '-etape-' . $ordre;
        $template->addEtapeToPhase(
            $phaseId,
            $etapeId,
            $titre,
            $description,
            $ordre,
            $obligatoire,
            $documentId
        );

        $this->entityManager->flush();

        return $template;
    }

    /**
     * Cree une instance de checklist pour une operation (Snapshot Pattern).
     * RG-031 : L'instance conserve une copie complete du template
     */
    public function creerInstancePourOperation(
        Operation $operation,
        ChecklistTemplate $template
    ): ChecklistInstance {
        // Verifier qu'il n'y a pas deja une instance
        if ($operation->getChecklistInstance() !== null) {
            return $operation->getChecklistInstance();
        }

        $instance = new ChecklistInstance();
        $instance->createSnapshotFromTemplate($template);
        $instance->setOperation($operation);

        $this->entityManager->persist($instance);
        $this->entityManager->flush();

        return $instance;
    }

    /**
     * Coche une etape de la checklist.
     * RG-033 : Persistance immediate de la progression
     * RG-032 : Verifie que la phase est accessible
     *
     * @throws \InvalidArgumentException Si l'etape n'existe pas ou la phase n'est pas accessible
     */
    public function cocherEtape(
        ChecklistInstance $instance,
        string $etapeId,
        Utilisateur $utilisateur
    ): ChecklistInstance {
        // Verifier que l'etape existe
        if (!$this->etapeExiste($instance, $etapeId)) {
            throw new \InvalidArgumentException(sprintf('Etape "%s" introuvable.', $etapeId));
        }

        // Verifier que la phase est accessible (RG-032)
        $phaseId = $this->getPhaseIdFromEtapeId($instance, $etapeId);
        if ($phaseId !== null && !$instance->isPhaseAccessible($phaseId)) {
            throw new \InvalidArgumentException('Phase non accessible. Terminez d\'abord la phase precedente.');
        }

        $instance->cocherEtape($etapeId, $utilisateur->getId());
        $this->entityManager->flush();

        return $instance;
    }

    /**
     * Decoche une etape de la checklist.
     * RG-033 : Persistance immediate
     */
    public function decocherEtape(
        ChecklistInstance $instance,
        string $etapeId
    ): ChecklistInstance {
        if (!$this->etapeExiste($instance, $etapeId)) {
            throw new \InvalidArgumentException(sprintf('Etape "%s" introuvable.', $etapeId));
        }

        $instance->decocherEtape($etapeId);
        $this->entityManager->flush();

        return $instance;
    }

    /**
     * Toggle (coche/decoche) une etape.
     */
    public function toggleEtape(
        ChecklistInstance $instance,
        string $etapeId,
        Utilisateur $utilisateur
    ): ChecklistInstance {
        if ($instance->isEtapeCochee($etapeId)) {
            return $this->decocherEtape($instance, $etapeId);
        } else {
            return $this->cocherEtape($instance, $etapeId, $utilisateur);
        }
    }

    /**
     * Calcule les statistiques de progression d'une instance.
     *
     * @return array{
     *     total: int,
     *     completed: int,
     *     percentage: float,
     *     is_complete: bool,
     *     phases: array<string, array{
     *         nom: string,
     *         total: int,
     *         completed: int,
     *         is_complete: bool,
     *         is_accessible: bool
     *     }>
     * }
     */
    public function getProgression(ChecklistInstance $instance): array
    {
        $phases = $instance->getPhases();
        $totalEtapes = 0;
        $etapesCochees = 0;
        $phasesStats = [];

        foreach ($phases as $phase) {
            $phaseTotal = count($phase['etapes'] ?? []);
            $phaseCompleted = 0;

            foreach ($phase['etapes'] ?? [] as $etape) {
                $totalEtapes++;
                if ($instance->isEtapeCochee($etape['id'])) {
                    $etapesCochees++;
                    $phaseCompleted++;
                }
            }

            $phasesStats[$phase['id']] = [
                'nom' => $phase['nom'],
                'total' => $phaseTotal,
                'completed' => $phaseCompleted,
                'is_complete' => $instance->isPhaseComplete($phase['id']),
                'is_accessible' => $instance->isPhaseAccessible($phase['id']),
            ];
        }

        $percentage = $totalEtapes > 0 ? round(($etapesCochees / $totalEtapes) * 100, 1) : 100.0;

        return [
            'total' => $totalEtapes,
            'completed' => $etapesCochees,
            'percentage' => $percentage,
            'is_complete' => $instance->isComplete(),
            'phases' => $phasesStats,
        ];
    }

    /**
     * Recupere les templates actifs.
     *
     * @return ChecklistTemplate[]
     */
    public function getTemplatesActifs(): array
    {
        return $this->templateRepository->findActifs();
    }

    /**
     * Recupere un template par son ID.
     */
    public function getTemplate(int $id): ?ChecklistTemplate
    {
        return $this->templateRepository->find($id);
    }

    /**
     * Active ou desactive un template.
     */
    public function toggleTemplateActif(ChecklistTemplate $template): ChecklistTemplate
    {
        $template->setActif(!$template->isActif());
        $this->entityManager->flush();

        return $template;
    }

    /**
     * Verifie si une etape existe dans l'instance.
     */
    private function etapeExiste(ChecklistInstance $instance, string $etapeId): bool
    {
        foreach ($instance->getPhases() as $phase) {
            foreach ($phase['etapes'] ?? [] as $etape) {
                if ($etape['id'] === $etapeId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Recupere l'ID de la phase contenant une etape.
     */
    private function getPhaseIdFromEtapeId(ChecklistInstance $instance, string $etapeId): ?string
    {
        foreach ($instance->getPhases() as $phase) {
            foreach ($phase['etapes'] ?? [] as $etape) {
                if ($etape['id'] === $etapeId) {
                    return $phase['id'];
                }
            }
        }

        return null;
    }

    /**
     * Cree un template de demonstration pour les tests.
     */
    public function creerTemplateDemo(): ChecklistTemplate
    {
        $template = $this->creerTemplate(
            'Migration Windows 11',
            'Checklist standard pour migration poste Windows 11'
        );

        // Phase 1 : Preparation
        $this->ajouterPhase($template, 'Preparation', true);
        $this->ajouterEtape($template, 'phase-1', 'Verifier le materiel necessaire', 'Cle USB, cable Ethernet, adaptateur');
        $this->ajouterEtape($template, 'phase-1', 'Informer l\'agent de l\'intervention', 'Confirmer sa disponibilite');

        // Phase 2 : Sauvegarde
        $this->ajouterPhase($template, 'Sauvegarde', true);
        $this->ajouterEtape($template, 'phase-2', 'Sauvegarder les profils utilisateur', 'Documents, Bureau, Favoris, Signatures');
        $this->ajouterEtape($template, 'phase-2', 'Verifier la sauvegarde OneDrive', 'Synchronisation complete avant migration');

        // Phase 3 : Migration
        $this->ajouterPhase($template, 'Migration', false);
        $this->ajouterEtape($template, 'phase-3', 'Lancer le script de migration', 'Executer migrate-w11.ps1 en admin');
        $this->ajouterEtape($template, 'phase-3', 'Attendre la fin de l\'installation', '~45 min - Ne pas interrompre');

        // Phase 4 : Verification
        $this->ajouterPhase($template, 'Verification', false);
        $this->ajouterEtape($template, 'phase-4', 'Verifier les applicatifs metier', 'Cristal, GED, Messagerie');
        $this->ajouterEtape($template, 'phase-4', 'Faire signer l\'agent', 'Validation de la recette');

        return $template;
    }
}
