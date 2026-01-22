<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Operation;
use App\Repository\DocumentRepository;
use App\Repository\OperationRepository;
use App\Service\ChecklistService;
use App\Service\DocumentService;
use App\Service\OperationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour l'interface terrain (Karim - Technicien IT).
 *
 * Regles metier implementees :
 * - RG-020 : Vue filtree sur les operations assignees au technicien connecte
 * - RG-017 : Transitions de statut via workflow
 * - RG-021 : Motif de report optionnel
 * - RG-080 : Triple signalisation (icone + couleur + texte)
 * - RG-082 : Touch targets 44x44px minimum, boutons 56px
 */
#[Route('/terrain')]
#[IsGranted('ROLE_TECHNICIEN')]
class TerrainController extends AbstractController
{
    public function __construct(
        private readonly OperationRepository $operationRepository,
        private readonly OperationService $operationService,
        private readonly ChecklistService $checklistService,
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentService $documentService,
    ) {
    }

    /**
     * Liste "Mes interventions" - Vue filtree pour le technicien connecte.
     * US-401 : Voir mes interventions du jour
     */
    #[Route('', name: 'terrain_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        // Recuperer les operations assignees au technicien
        $operations = $this->operationRepository->findByTechnicien($user->getId());

        // Grouper par statut pour l'affichage
        $groupedOperations = $this->groupOperationsByStatus($operations);

        // Calculer les stats du jour
        $stats = $this->calculateDayStats($operations);

        return $this->render('terrain/index.html.twig', [
            'operations' => $operations,
            'grouped_operations' => $groupedOperations,
            'stats' => $stats,
            'today' => new \DateTimeImmutable(),
        ]);
    }

    /**
     * Detail d'une intervention.
     * US-402 : Voir le detail d'une operation assignee
     */
    #[Route('/{id}', name: 'terrain_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Operation $operation): Response
    {
        // Verifier que l'operation est assignee au technicien connecte
        $this->denyAccessUnlessGranted('view', $operation);

        $transitions = $this->operationService->getTransitionsDisponibles($operation);

        // Calculer la progression de la checklist si elle existe
        $checklistProgression = null;
        if ($operation->getChecklistInstance()) {
            $checklistProgression = $this->checklistService->getProgression($operation->getChecklistInstance());
        }

        // T-1106, T-1107 : Charger les documents de la campagne pour la checklist
        $documents = $this->documentRepository->findByCampagne($operation->getCampagne()->getId());
        $documentsById = [];
        foreach ($documents as $doc) {
            $documentsById[$doc->getId()] = $doc;
        }

        return $this->render('terrain/show.html.twig', [
            'operation' => $operation,
            'transitions' => $transitions,
            'checklist_progression' => $checklistProgression,
            'documents' => $documentsById,
        ]);
    }

    /**
     * Toggle (coche/decoche) une etape de la checklist.
     * US-501 : Cocher une etape de checklist
     * T-605 : Retourne un Turbo Frame pour update sans reload
     * RG-082 : Touch targets 48x48px (gere dans le template)
     */
    #[Route('/{id}/checklist/toggle/{etapeId}', name: 'terrain_checklist_toggle', methods: ['POST'])]
    public function toggleEtape(
        Request $request,
        Operation $operation,
        string $etapeId
    ): Response {
        $this->denyAccessUnlessGranted('edit', $operation);

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('checklist_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        $instance = $operation->getChecklistInstance();
        if (!$instance) {
            $this->addFlash('error', 'Pas de checklist pour cette operation.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        try {
            $this->checklistService->toggleEtape($instance, $etapeId, $this->getUser());
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        // Calculer la nouvelle progression
        $progression = $this->checklistService->getProgression($instance);

        // T-1106, T-1107 : Charger les documents pour la checklist
        $documents = $this->documentRepository->findByCampagne($operation->getCampagne()->getId());
        $documentsById = [];
        foreach ($documents as $doc) {
            $documentsById[$doc->getId()] = $doc;
        }

        // T-605 : Si requete Turbo, retourner uniquement le fragment
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('terrain/_checklist.html.twig', [
                'operation' => $operation,
                'checklist_progression' => $progression,
                'documents' => $documentsById,
            ]);
        }

        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
    }

    /**
     * T-1106 : Consulter un document depuis la checklist
     * Affiche le document inline (PDF) ou force le telechargement (autres formats)
     */
    #[Route('/{id}/document/{documentId}', name: 'terrain_document_view', methods: ['GET'])]
    public function viewDocument(Operation $operation, int $documentId): Response
    {
        $this->denyAccessUnlessGranted('view', $operation);

        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            throw $this->createNotFoundException('Document non trouve.');
        }

        // Verifier que le document appartient a la campagne de l'operation
        if ($document->getCampagne()->getId() !== $operation->getCampagne()->getId()) {
            throw $this->createAccessDeniedException('Document non accessible.');
        }

        if (!$this->documentService->fileExists($document)) {
            throw $this->createNotFoundException('Fichier non trouve sur le serveur.');
        }

        $filePath = $this->documentService->getFilePath($document);
        $response = new BinaryFileResponse($filePath);

        // PDF : affichage inline, autres : telechargement
        if ($document->getExtension() === 'pdf') {
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                $document->getNomOriginal()
            );
        } else {
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $document->getNomOriginal()
            );
        }

        return $response;
    }

    /**
     * T-1107 : Telecharger un script depuis la checklist
     * Force le telechargement du fichier (pour scripts ps1, bat, exe)
     */
    #[Route('/{id}/document/{documentId}/download', name: 'terrain_document_download', methods: ['GET'])]
    public function downloadDocument(Operation $operation, int $documentId): Response
    {
        $this->denyAccessUnlessGranted('view', $operation);

        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            throw $this->createNotFoundException('Document non trouve.');
        }

        // Verifier que le document appartient a la campagne de l'operation
        if ($document->getCampagne()->getId() !== $operation->getCampagne()->getId()) {
            throw $this->createAccessDeniedException('Document non accessible.');
        }

        if (!$this->documentService->fileExists($document)) {
            throw $this->createNotFoundException('Fichier non trouve sur le serveur.');
        }

        $filePath = $this->documentService->getFilePath($document);
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getNomOriginal()
        );

        return $response;
    }

    /**
     * Changer le statut d'une operation en 1 clic.
     * US-403 : RG-017, RG-021
     */
    #[Route('/{id}/transition/{transition}', name: 'terrain_transition', methods: ['POST'])]
    public function transition(
        Request $request,
        Operation $operation,
        string $transition
    ): Response {
        // Verifier que l'operation est assignee au technicien connecte
        $this->denyAccessUnlessGranted('edit', $operation);

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('transition_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        // Recuperer le motif si c'est un report (RG-021)
        $motif = null;
        if ($transition === 'reporter') {
            $motif = $request->request->get('motif');
        }

        // Appliquer la transition
        $success = $this->operationService->appliquerTransition($operation, $transition, $motif);

        if ($success) {
            $this->addFlash('success', sprintf('Operation %s mise a jour.', $operation->getMatricule()));
        } else {
            $this->addFlash('error', 'Transition non autorisee.');
        }

        // US-404 : Retour automatique apres action
        $returnTo = $request->request->get('return_to', 'terrain_index');

        if ($returnTo === 'terrain_show') {
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        return $this->redirectToRoute('terrain_index');
    }

    /**
     * Demarrer une intervention (raccourci).
     * Transition planifie -> en_cours
     */
    #[Route('/{id}/demarrer', name: 'terrain_demarrer', methods: ['POST'])]
    public function demarrer(Request $request, Operation $operation): Response
    {
        $this->denyAccessUnlessGranted('edit', $operation);

        if (!$this->isCsrfTokenValid('demarrer_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_index');
        }

        $success = $this->operationService->appliquerTransition($operation, 'demarrer');

        if ($success) {
            $this->addFlash('success', sprintf('Intervention %s demarree.', $operation->getMatricule()));
            // Rediriger vers le detail pour commencer a travailler
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        $this->addFlash('error', 'Impossible de demarrer cette intervention.');
        return $this->redirectToRoute('terrain_index');
    }

    /**
     * Terminer une intervention (raccourci).
     * Transition en_cours -> realise
     */
    #[Route('/{id}/terminer', name: 'terrain_terminer', methods: ['POST'])]
    public function terminer(Request $request, Operation $operation): Response
    {
        $this->denyAccessUnlessGranted('edit', $operation);

        if (!$this->isCsrfTokenValid('terminer_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        $success = $this->operationService->appliquerTransition($operation, 'realiser');

        if ($success) {
            $this->addFlash('success', sprintf('Intervention %s terminee.', $operation->getMatricule()));
            // US-505 : Retour automatique a la liste
            return $this->redirectToRoute('terrain_index');
        }

        $this->addFlash('error', 'Impossible de terminer cette intervention.');
        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
    }

    /**
     * Reporter une intervention avec motif.
     * Transition en_cours -> reporte (RG-021)
     */
    #[Route('/{id}/reporter', name: 'terrain_reporter', methods: ['POST'])]
    public function reporter(Request $request, Operation $operation): Response
    {
        $this->denyAccessUnlessGranted('edit', $operation);

        if (!$this->isCsrfTokenValid('reporter_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        $motif = $request->request->get('motif');

        $success = $this->operationService->appliquerTransition($operation, 'reporter', $motif);

        if ($success) {
            $this->addFlash('warning', sprintf('Intervention %s reportee.', $operation->getMatricule()));
            // US-505 : Retour automatique a la liste
            return $this->redirectToRoute('terrain_index');
        }

        $this->addFlash('error', 'Impossible de reporter cette intervention.');
        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
    }

    /**
     * Signaler un probleme (a remedier).
     * Transition en_cours -> a_remedier
     */
    #[Route('/{id}/probleme', name: 'terrain_probleme', methods: ['POST'])]
    public function probleme(Request $request, Operation $operation): Response
    {
        $this->denyAccessUnlessGranted('edit', $operation);

        if (!$this->isCsrfTokenValid('probleme_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        $motif = $request->request->get('motif');

        $success = $this->operationService->appliquerTransition($operation, 'remedier', $motif);

        if ($success) {
            $this->addFlash('danger', sprintf('Probleme signale pour %s.', $operation->getMatricule()));
            // US-505 : Retour automatique a la liste
            return $this->redirectToRoute('terrain_index');
        }

        $this->addFlash('error', 'Impossible de signaler le probleme.');
        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
    }

    /**
     * Groupe les operations par statut pour l'affichage.
     *
     * @param Operation[] $operations
     * @return array<string, Operation[]>
     */
    private function groupOperationsByStatus(array $operations): array
    {
        $grouped = [
            'next' => null, // Prochaine intervention (premiere planifiee)
            Operation::STATUT_PLANIFIE => [],
            Operation::STATUT_EN_COURS => [],
            Operation::STATUT_REALISE => [],
            Operation::STATUT_REPORTE => [],
            Operation::STATUT_A_REMEDIER => [],
            Operation::STATUT_A_PLANIFIER => [],
        ];

        foreach ($operations as $operation) {
            $statut = $operation->getStatut();

            // Identifier la prochaine intervention (premiere planifiee)
            if ($statut === Operation::STATUT_PLANIFIE && $grouped['next'] === null) {
                $grouped['next'] = $operation;
                continue;
            }

            // En cours passe en premier
            if ($statut === Operation::STATUT_EN_COURS && $grouped['next'] === null) {
                $grouped['next'] = $operation;
                continue;
            }

            if (isset($grouped[$statut])) {
                $grouped[$statut][] = $operation;
            }
        }

        return $grouped;
    }

    /**
     * Calcule les statistiques du jour pour le technicien.
     *
     * @param Operation[] $operations
     * @return array{total: int, realise: int, planifie: int, reporte: int, en_cours: int}
     */
    private function calculateDayStats(array $operations): array
    {
        $stats = [
            'total' => count($operations),
            'realise' => 0,
            'planifie' => 0,
            'reporte' => 0,
            'en_cours' => 0,
            'a_remedier' => 0,
        ];

        foreach ($operations as $operation) {
            switch ($operation->getStatut()) {
                case Operation::STATUT_REALISE:
                    $stats['realise']++;
                    break;
                case Operation::STATUT_PLANIFIE:
                    $stats['planifie']++;
                    break;
                case Operation::STATUT_REPORTE:
                    $stats['reporte']++;
                    break;
                case Operation::STATUT_EN_COURS:
                    $stats['en_cours']++;
                    break;
                case Operation::STATUT_A_REMEDIER:
                    $stats['a_remedier']++;
                    break;
            }
        }

        return $stats;
    }
}
