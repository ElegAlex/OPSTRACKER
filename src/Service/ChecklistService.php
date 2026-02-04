<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\ChecklistInstance;
use App\Entity\ChecklistTemplate;
use App\Entity\Operation;
use App\Entity\Utilisateur;
use App\Repository\ChecklistTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service metier pour la gestion des checklists.
 *
 * Architecture retroactive :
 * - La structure de la checklist est stockee dans Campagne.checklistStructure
 * - Les modifications impactent toutes les operations immediatement
 * - ChecklistInstance ne stocke que les IDs des etapes cochees
 *
 * Regles metier implementees :
 * - RG-030 : Template = Nom + Version + Etapes ordonnees + Phases optionnelles
 * - RG-032 : Phases verrouillables (phase suivante accessible si precedente complete)
 * - RG-033 : Persistance progression - chaque coche est sauvegardee immediatement
 *
 * Regles Sophie (gestionnaire) :
 * - Peut ajouter des etapes a tout moment
 * - Peut desactiver/reactiver des etapes
 * - Ne peut pas modifier le contenu ni supprimer des etapes
 */
class ChecklistService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChecklistTemplateRepository $templateRepository,
    ) {
    }

    /**
     * Cree un nouveau template de checklist.
     * RG-030 : Template = Nom + Version + Etapes ordonnees + Phases optionnelles
     *
     * @param array<string, mixed> $etapes
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

    // ========================================================================
    // GESTION CHECKLIST AU NIVEAU CAMPAGNE (Architecture retroactive)
    // ========================================================================

    /**
     * Copie la structure d'un template vers une campagne.
     * Ajoute le champ 'actif' a chaque etape pour permettre la desactivation.
     */
    public function copierTemplateVersCampagne(Campagne $campagne, ChecklistTemplate $template): Campagne
    {
        $structure = $template->getEtapes();

        // Ajouter le champ 'actif' a chaque etape
        foreach ($structure['phases'] as &$phase) {
            foreach ($phase['etapes'] as &$etape) {
                $etape['actif'] = true;
                $etape['createdAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                $etape['disabledAt'] = null;
            }
        }

        // Tracer l'origine du template
        $structure['sourceTemplateId'] = $template->getId();
        $structure['sourceTemplateVersion'] = $template->getVersion();

        $campagne->setChecklistStructure($structure);
        $this->entityManager->flush();

        return $campagne;
    }

    /**
     * Desactive une etape dans la checklist de la campagne.
     * RG-Sophie : Peut desactiver mais pas supprimer
     *
     * @throws \InvalidArgumentException Si pas de checklist ou etape introuvable
     */
    public function desactiverEtape(Campagne $campagne, string $etapeId): void
    {
        $structure = $campagne->getChecklistStructure();
        if (!$structure) {
            throw new \InvalidArgumentException('Aucune checklist configuree pour cette campagne.');
        }

        $found = false;
        foreach ($structure['phases'] as $phaseIndex => $phase) {
            foreach ($phase['etapes'] as $etapeIndex => $etape) {
                if ($etape['id'] === $etapeId) {
                    $structure['phases'][$phaseIndex]['etapes'][$etapeIndex]['actif'] = false;
                    $structure['phases'][$phaseIndex]['etapes'][$etapeIndex]['disabledAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                    $found = true;
                    break 2;
                }
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException(sprintf('Etape "%s" introuvable.', $etapeId));
        }

        $campagne->setChecklistStructure($structure);
        $this->entityManager->flush();
    }

    /**
     * Reactive une etape precedemment desactivee.
     *
     * @throws \InvalidArgumentException Si pas de checklist ou etape introuvable
     */
    public function reactiverEtape(Campagne $campagne, string $etapeId): void
    {
        $structure = $campagne->getChecklistStructure();
        if (!$structure) {
            throw new \InvalidArgumentException('Aucune checklist configuree pour cette campagne.');
        }

        foreach ($structure['phases'] as $phaseIndex => $phase) {
            foreach ($phase['etapes'] as $etapeIndex => $etape) {
                if ($etape['id'] === $etapeId) {
                    $structure['phases'][$phaseIndex]['etapes'][$etapeIndex]['actif'] = true;
                    $structure['phases'][$phaseIndex]['etapes'][$etapeIndex]['disabledAt'] = null;
                    $campagne->setChecklistStructure($structure);
                    $this->entityManager->flush();

                    return;
                }
            }
        }

        throw new \InvalidArgumentException(sprintf('Etape "%s" introuvable.', $etapeId));
    }

    /**
     * Ajoute une nouvelle etape a une phase de la campagne.
     * RG-Sophie : Peut ajouter des etapes a tout moment
     *
     * @return string L'ID de l'etape creee
     *
     * @throws \InvalidArgumentException Si pas de checklist ou phase introuvable
     */
    public function ajouterEtapeCampagne(
        Campagne $campagne,
        string $phaseId,
        string $titre,
        ?string $description = null,
        bool $obligatoire = true,
        ?int $documentId = null,
        ?string $champCible = null
    ): string {
        $structure = $campagne->getChecklistStructure();
        if (!$structure) {
            throw new \InvalidArgumentException('Aucune checklist configuree pour cette campagne.');
        }

        $phaseFound = false;
        $maxOrdre = 0;
        $etapeId = '';

        foreach ($structure['phases'] as &$phase) {
            if ($phase['id'] === $phaseId) {
                $phaseFound = true;

                // Trouver le max ordre
                foreach ($phase['etapes'] as $e) {
                    if (($e['ordre'] ?? 0) > $maxOrdre) {
                        $maxOrdre = $e['ordre'];
                    }
                }

                // Generer un ID unique
                $etapeId = $phaseId.'-etape-'.uniqid();

                $phase['etapes'][] = [
                    'id' => $etapeId,
                    'titre' => $titre,
                    'description' => $description,
                    'ordre' => $maxOrdre + 1,
                    'obligatoire' => $obligatoire,
                    'documentId' => $documentId,
                    'champCible' => $champCible,
                    'actif' => true,
                    'createdAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    'disabledAt' => null,
                ];

                break;
            }
        }

        if (!$phaseFound) {
            throw new \InvalidArgumentException(sprintf('Phase "%s" introuvable.', $phaseId));
        }

        $campagne->setChecklistStructure($structure);
        $this->entityManager->flush();

        return $etapeId;
    }

    // ========================================================================
    // GESTION INSTANCES (pour retrocompatibilite)
    // ========================================================================

    /**
     * Cree une instance de checklist pour une operation.
     * Architecture retroactive : la structure est lue depuis Campagne.checklistStructure
     *
     * @param Operation              $operation L'operation
     * @param ChecklistTemplate|null $template  (optionnel, pour retrocompatibilite)
     */
    public function creerInstancePourOperation(
        Operation $operation,
        ?ChecklistTemplate $template = null
    ): ?ChecklistInstance {
        // Verifier qu'il n'y a pas deja une instance
        if ($operation->getChecklistInstance() !== null) {
            return $operation->getChecklistInstance();
        }

        $campagne = $operation->getCampagne();

        // Nouvelle architecture : verifier si la campagne a une structure de checklist
        if ($campagne && $campagne->hasChecklistStructure()) {
            $instance = new ChecklistInstance();
            $instance->setOperation($operation);
            // Plus de snapshot - la structure est lue depuis Campagne

            $this->entityManager->persist($instance);
            $this->entityManager->flush();

            return $instance;
        }

        // Retrocompatibilite : utiliser le template fourni ou celui de la campagne
        if (!$template && $campagne) {
            $template = $campagne->getChecklistTemplate();
        }

        if (!$template) {
            return null;
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
     * @throws \InvalidArgumentException Si l'etape n'existe pas, est desactivee, ou la phase n'est pas accessible
     */
    public function cocherEtape(
        ChecklistInstance $instance,
        string $etapeId,
        Utilisateur $utilisateur
    ): ChecklistInstance {
        // Verifier que l'etape existe et est active
        if (!$this->etapeExiste($instance, $etapeId)) {
            throw new \InvalidArgumentException(sprintf('Etape "%s" introuvable ou desactivee.', $etapeId));
        }

        // Verifier que la phase est accessible (RG-032)
        $phaseId = $this->getPhaseIdFromEtapeId($instance, $etapeId);
        if ($phaseId !== null && !$this->isPhaseAccessible($instance, $phaseId)) {
            throw new \InvalidArgumentException('Phase non accessible. Terminez d\'abord la phase precedente.');
        }

        $utilisateurId = $utilisateur->getId();
        if ($utilisateurId === null) {
            throw new \InvalidArgumentException('L\'utilisateur doit avoir un ID.');
        }
        $instance->cocherEtape($etapeId, $utilisateurId);
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
     * Architecture retroactive : lit la structure depuis Campagne.checklistStructure
     * IMPORTANT : Les etapes desactivees sont EXCLUES du compteur
     *
     * @return array{
     *     total: int,
     *     completed: int,
     *     percentage: float,
     *     is_complete: bool,
     *     phases: array<string, array{
     *         id: string,
     *         nom: string,
     *         verrouillable: bool,
     *         total: int,
     *         completed: int,
     *         is_complete: bool,
     *         is_accessible: bool,
     *         etapes: array<int, array{id: string, titre: string, description: ?string, obligatoire: bool, documentId: ?int, champCible: ?string, actif: bool, cochee: bool, cocheeMaisDesactivee: bool}>
     *     }>
     * }
     */
    public function getProgression(ChecklistInstance $instance): array
    {
        $operation = $instance->getOperation();
        $campagne = $operation?->getCampagne();

        // Nouvelle architecture : lire depuis Campagne.checklistStructure
        if ($campagne !== null && $campagne->hasChecklistStructure()) {
            return $this->getProgressionFromCampagne($instance, $campagne);
        }

        // Fallback retrocompatibilite : lire depuis le snapshot
        return $this->getProgressionFromSnapshot($instance);
    }

    /**
     * Calcule la progression depuis Campagne.checklistStructure (nouvelle architecture)
     * Les etapes desactivees sont EXCLUES du compteur mais incluses dans l'affichage
     *
     * @return array{
     *     total: int,
     *     completed: int,
     *     percentage: float,
     *     is_complete: bool,
     *     phases: array<string, array{
     *         id: string,
     *         nom: string,
     *         verrouillable: bool,
     *         total: int,
     *         completed: int,
     *         is_complete: bool,
     *         is_accessible: bool,
     *         etapes: array<int, array{id: string, titre: string, description: ?string, obligatoire: bool, documentId: ?int, champCible: ?string, actif: bool, cochee: bool, cocheeMaisDesactivee: bool}>
     *     }>
     * }
     */
    private function getProgressionFromCampagne(ChecklistInstance $instance, Campagne $campagne): array
    {
        $structure = $campagne->getChecklistStructure();
        $etapesCocheesIds = $instance->getEtapesCocheesIds();

        // Fallback: si etapesCochees est vide, utiliser l'ancien format progression
        if (empty($etapesCocheesIds)) {
            foreach ($instance->getProgression() as $etapeId => $data) {
                if ($data['cochee'] ?? false) {
                    $etapesCocheesIds[] = $etapeId;
                }
            }
        }

        $totalEtapes = 0;
        $etapesCompletees = 0;
        $phasesStats = [];

        foreach ($structure['phases'] ?? [] as $phase) {
            $phaseTotal = 0;
            $phaseCompleted = 0;
            $phaseEtapes = [];

            foreach ($phase['etapes'] ?? [] as $etape) {
                $isActif = $etape['actif'] ?? true;
                $isCochee = in_array($etape['id'], $etapesCocheesIds, true);

                // Recuperer le champCible depuis le mapping de la campagne (pas du template)
                $champCibleFromMapping = $campagne->getChampCibleForEtape($etape['id']);

                // Info pour l'affichage (toutes les etapes)
                $phaseEtapes[] = [
                    'id' => $etape['id'],
                    'titre' => $etape['titre'],
                    'description' => $etape['description'] ?? null,
                    'obligatoire' => $etape['obligatoire'] ?? true,
                    'documentId' => $etape['documentId'] ?? null,
                    'champCible' => $champCibleFromMapping,
                    'actif' => $isActif,
                    'cochee' => $isCochee,
                    'cocheeMaisDesactivee' => !$isActif && $isCochee,
                ];

                // Comptage UNIQUEMENT des etapes actives
                if ($isActif) {
                    ++$totalEtapes;
                    ++$phaseTotal;

                    if ($isCochee) {
                        ++$etapesCompletees;
                        ++$phaseCompleted;
                    }
                }
            }

            $phasesStats[$phase['id']] = [
                'id' => $phase['id'],
                'nom' => $phase['nom'],
                'verrouillable' => $phase['verrouillable'] ?? false,
                'total' => $phaseTotal,
                'completed' => $phaseCompleted,
                'is_complete' => $phaseTotal > 0 && $phaseTotal === $phaseCompleted,
                'is_accessible' => true, // Sera calcule apres
                'etapes' => $phaseEtapes,
            ];
        }

        // Calculer l'accessibilite des phases (RG-032)
        $previousComplete = true;
        foreach ($phasesStats as $phaseId => &$stats) {
            $stats['is_accessible'] = $previousComplete;
            if ($stats['verrouillable']) {
                $previousComplete = $stats['is_complete'];
            }
        }

        $percentage = $totalEtapes > 0 ? round(($etapesCompletees / $totalEtapes) * 100, 1) : 100.0;

        return [
            'total' => $totalEtapes,
            'completed' => $etapesCompletees,
            'percentage' => $percentage,
            'is_complete' => $totalEtapes > 0 && $totalEtapes === $etapesCompletees,
            'phases' => $phasesStats,
        ];
    }

    /**
     * Calcule la progression depuis le snapshot (retrocompatibilite)
     *
     * @return array{
     *     total: int,
     *     completed: int,
     *     percentage: float,
     *     is_complete: bool,
     *     phases: array<string, array{
     *         id: string,
     *         nom: string,
     *         verrouillable: bool,
     *         total: int,
     *         completed: int,
     *         is_complete: bool,
     *         is_accessible: bool,
     *         etapes: array<int, array{id: string, titre: string, description: ?string, obligatoire: bool, documentId: ?int, champCible: ?string, actif: bool, cochee: bool, cocheeMaisDesactivee: bool}>
     *     }>
     * }
     */
    private function getProgressionFromSnapshot(ChecklistInstance $instance): array
    {
        $phases = $instance->getPhases();
        $totalEtapes = 0;
        $etapesCochees = 0;
        $phasesStats = [];

        foreach ($phases as $phase) {
            $phaseTotal = count($phase['etapes'] ?? []);
            $phaseCompleted = 0;
            $phaseEtapes = [];

            foreach ($phase['etapes'] ?? [] as $etape) {
                ++$totalEtapes;
                $isCochee = $instance->isEtapeCochee($etape['id']);
                if ($isCochee) {
                    ++$etapesCochees;
                    ++$phaseCompleted;
                }

                $phaseEtapes[] = [
                    'id' => $etape['id'],
                    'titre' => $etape['titre'],
                    'description' => $etape['description'] ?? null,
                    'obligatoire' => $etape['obligatoire'] ?? true,
                    'documentId' => $etape['documentId'] ?? null,
                    'champCible' => $etape['champCible'] ?? null,
                    'actif' => true,
                    'cochee' => $isCochee,
                    'cocheeMaisDesactivee' => false,
                ];
            }

            $phasesStats[$phase['id']] = [
                'id' => $phase['id'],
                'nom' => $phase['nom'],
                'verrouillable' => $phase['verrouillable'] ?? false,
                'total' => $phaseTotal,
                'completed' => $phaseCompleted,
                'is_complete' => $instance->isPhaseComplete($phase['id']),
                'is_accessible' => $instance->isPhaseAccessible($phase['id']),
                'etapes' => $phaseEtapes,
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
     * Verifie si une etape existe et est active.
     * Nouvelle architecture : lit depuis Campagne.checklistStructure
     */
    private function etapeExiste(ChecklistInstance $instance, string $etapeId): bool
    {
        $campagne = $instance->getOperation()?->getCampagne();

        // Nouvelle architecture : lire depuis Campagne
        if ($campagne && $campagne->hasChecklistStructure()) {
            $structure = $campagne->getChecklistStructure();
            foreach ($structure['phases'] ?? [] as $phase) {
                foreach ($phase['etapes'] ?? [] as $etape) {
                    if ($etape['id'] === $etapeId && ($etape['actif'] ?? true)) {
                        return true;
                    }
                }
            }

            return false;
        }

        // Fallback : lire depuis le snapshot
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
     * Nouvelle architecture : lit depuis Campagne.checklistStructure
     */
    private function getPhaseIdFromEtapeId(ChecklistInstance $instance, string $etapeId): ?string
    {
        $campagne = $instance->getOperation()?->getCampagne();

        // Nouvelle architecture : lire depuis Campagne
        if ($campagne && $campagne->hasChecklistStructure()) {
            $structure = $campagne->getChecklistStructure();
            foreach ($structure['phases'] ?? [] as $phase) {
                foreach ($phase['etapes'] ?? [] as $etape) {
                    if ($etape['id'] === $etapeId) {
                        return $phase['id'];
                    }
                }
            }

            return null;
        }

        // Fallback : lire depuis le snapshot
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
     * Verifie si une phase est accessible (RG-032)
     * Nouvelle architecture : calcule depuis Campagne.checklistStructure
     */
    private function isPhaseAccessible(ChecklistInstance $instance, string $phaseId): bool
    {
        $campagne = $instance->getOperation()?->getCampagne();

        // Nouvelle architecture
        if ($campagne && $campagne->hasChecklistStructure()) {
            $progression = $this->getProgressionFromCampagne($instance, $campagne);

            return $progression['phases'][$phaseId]['is_accessible'] ?? true;
        }

        // Fallback : utiliser la methode de l'instance
        return $instance->isPhaseAccessible($phaseId);
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
