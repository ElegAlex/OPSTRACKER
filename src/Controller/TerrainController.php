<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Operation;
use App\Entity\Utilisateur;
use App\Repository\DocumentRepository;
use App\Repository\OperationRepository;
use App\Service\ChecklistService;
use App\Service\DocumentService;
use App\Service\OperationService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Liste "Mes interventions" - Vue consolidee multi-campagnes.
     * US-401 : Voir mes interventions du jour
     * Feature : KPIs + section retards + section aujourd'hui + section a venir + section terminees
     */
    #[Route('', name: 'terrain_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');

        // Operations en retard (toutes campagnes)
        $operationsRetard = $this->operationRepository->findRetardForTechnicien($user->getId());

        // Operations aujourd'hui (toutes campagnes)
        $operationsAujourdhui = $this->operationRepository->findTodayForTechnicien($user->getId(), $today);

        // Operations a venir (toutes campagnes)
        $operationsAVenir = $this->operationRepository->findAVenirForTechnicien($user->getId(), $today);

        // Operations terminees (30 derniers jours)
        $operationsTerminees = $this->operationRepository->findTermineesForTechnicien($user->getId());

        // KPIs (toutes les operations : retard + aujourd'hui + a venir)
        $kpis = [
            'total' => count($operationsRetard) + count($operationsAujourdhui) + count($operationsAVenir),
            'a_venir' => count($operationsAVenir),
            'a_faire' => count($operationsAujourdhui),
            'retard' => count($operationsRetard),
            'terminees' => count($operationsTerminees),
        ];

        // Mapping campagne -> index de couleur pour différenciation visuelle
        $toutesOperations = array_merge($operationsRetard, $operationsAujourdhui, $operationsAVenir, $operationsTerminees);
        $campagneColors = [];
        foreach ($toutesOperations as $op) {
            $campagneId = $op->getCampagne()->getId();
            if (!isset($campagneColors[$campagneId])) {
                $campagneColors[$campagneId] = count($campagneColors);
            }
        }

        return $this->render('terrain/index.html.twig', [
            'kpis' => $kpis,
            'operationsRetard' => $operationsRetard,
            'operationsAujourdhui' => $operationsAujourdhui,
            'operationsAVenir' => $operationsAVenir,
            'operationsTerminees' => $operationsTerminees,
            'today' => $today,
            'campagneColors' => $campagneColors,
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
        $campagne = $operation->getCampagne();

        // Nouvelle architecture : vérifier si la campagne a une structure de checklist
        if ($campagne->hasChecklistStructure()) {
            $instance = $operation->getChecklistInstance();
            if (!$instance) {
                $instance = $this->checklistService->creerInstancePourOperation($operation);
            }
            if ($instance) {
                $checklistProgression = $this->checklistService->getProgression($instance);
            }
        } elseif ($operation->getChecklistInstance()) {
            // Fallback : ancienne architecture avec snapshot
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
     * Sauvegarde la valeur d'un champ de saisie checklist sans toggle.
     */
    #[Route('/{id}/checklist/save/{etapeId}', name: 'terrain_checklist_save_field', methods: ['POST'])]
    public function saveChecklistField(
        Request $request,
        Operation $operation,
        string $etapeId
    ): Response {
        $this->denyAccessUnlessGranted('edit', $operation);

        if (!$this->isCsrfTokenValid('checklist_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        $champCible = $this->getChampCibleForEtape($operation, $etapeId);
        if ($champCible) {
            $valeur = trim($request->request->get('valeur_champ', ''));
            $operation->setDonneePersonnalisee($champCible, $valeur);
            $this->entityManager->flush();
            $this->addFlash('success', 'Valeur enregistree.');
        }

        // Turbo Frame : retourner le fragment mis a jour
        if ($request->headers->has('Turbo-Frame')) {
            $instance = $operation->getChecklistInstance();
            $progression = $instance ? $this->checklistService->getProgression($instance) : null;

            $documents = $this->documentRepository->findByCampagne($operation->getCampagne()->getId());
            $documentsById = [];
            foreach ($documents as $doc) {
                $documentsById[$doc->getId()] = $doc;
            }

            return $this->render('terrain/_checklist.html.twig', [
                'operation' => $operation,
                'checklist_progression' => $progression,
                'documents' => $documentsById,
            ]);
        }

        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
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

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        try {
            $this->checklistService->toggleEtape($instance, $etapeId, $currentUser);

            // Traitement champ de saisie si present
            $valeurChamp = $request->request->get('valeur_champ');
            if ($valeurChamp !== null) {
                $champCible = $this->getChampCibleForEtape($operation, $etapeId);
                if ($champCible) {
                    $valeur = trim($valeurChamp);
                    if ($valeur !== '') {
                        $operation->setDonneePersonnalisee($champCible, $valeur);
                    }
                }
            }
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
            $this->addFlash('success', sprintf('Operation %s mise a jour.', $operation->getDisplayIdentifier() ?? 'Operation'));
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
            $this->addFlash('success', sprintf('Intervention %s demarree.', $operation->getDisplayIdentifier() ?? 'Operation'));
            // Rediriger vers le detail pour commencer a travailler
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        $this->addFlash('error', 'Impossible de demarrer cette intervention.');
        return $this->redirectToRoute('terrain_index');
    }

    /**
     * Terminer une intervention avec saisie de duree.
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

        // Saisie duree uniquement si activee sur la campagne
        if ($operation->getCampagne()->isSaisieTempsActivee()) {
            /** @var Utilisateur|null $currentUser */
            $currentUser = $this->getUser();
            $dureeMinutes = $request->request->getInt('duree_minutes', 0);
            $operation->setDureeInterventionMinutes($dureeMinutes, $currentUser);
        }

        $success = $this->operationService->appliquerTransition($operation, 'realiser');

        if ($success) {
            $this->addFlash('success', sprintf('Intervention %s terminee.', $operation->getDisplayIdentifier() ?? 'Operation'));
            // US-505 : Retour automatique a la liste
            return $this->redirectToRoute('terrain_index');
        }

        $this->addFlash('error', 'Impossible de terminer cette intervention.');
        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
    }

    /**
     * Modifier la duree d'intervention a posteriori.
     * Accessible uniquement pour les operations terminees.
     */
    #[Route('/{id}/duree', name: 'terrain_update_duree', methods: ['POST'])]
    public function updateDuree(Request $request, Operation $operation): Response
    {
        $this->denyAccessUnlessGranted('edit', $operation);

        if (!$this->isCsrfTokenValid('duree' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        /** @var Utilisateur|null $currentUser */
        $currentUser = $this->getUser();
        $dureeMinutes = $request->request->getInt('duree_minutes', 0);
        $operation->setDureeInterventionMinutes($dureeMinutes, $currentUser);

        $this->entityManager->flush();

        $this->addFlash('success', 'Duree mise a jour.');

        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
    }

    /**
     * Reporter une intervention avec motif et date optionnelle.
     * - Avec nouvelle date : reporte + nouvelle datePlanifiee
     * - Sans nouvelle date : reporte + datePlanifiee = null (RG-021)
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
        $dateStr = $request->request->get('nouvelle_date');
        $heureStr = $request->request->get('nouvelle_heure');

        // Determiner la nouvelle date (peut etre null = report sans date)
        $nouvelleDatePlanifiee = null;

        if ($dateStr !== null && $dateStr !== '') {
            try {
                $datetime = $dateStr;
                if ($heureStr !== null && $heureStr !== '') {
                    $datetime .= ' ' . $heureStr;
                } else {
                    $datetime .= ' 09:00';
                }
                $nouvelleDatePlanifiee = new \DateTimeImmutable($datetime);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Format de date invalide.');
                return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
            }
        }

        $success = $this->operationService->appliquerTransition($operation, 'reporter', $motif, $nouvelleDatePlanifiee);

        if ($success) {
            if ($nouvelleDatePlanifiee !== null) {
                $this->addFlash('warning', sprintf(
                    'Intervention %s reportee au %s.',
                    $operation->getDisplayIdentifier() ?? 'Operation',
                    $nouvelleDatePlanifiee->format('d/m/Y H:i')
                ));
            } else {
                $this->addFlash('warning', sprintf(
                    'Intervention %s reportee (sans nouvelle date).',
                    $operation->getDisplayIdentifier() ?? 'Operation'
                ));
            }
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
            $this->addFlash('danger', sprintf('Probleme signale pour %s.', $operation->getDisplayIdentifier() ?? 'Operation'));
            // US-505 : Retour automatique a la liste
            return $this->redirectToRoute('terrain_index');
        }

        $this->addFlash('error', 'Impossible de signaler le probleme.');
        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
    }

    /**
     * Replanifier une intervention (depuis reporte ou a_remedier).
     * Permet a Karim de reprendre une operation problematique.
     * Transition reporte|a_remedier -> planifie
     */
    #[Route('/{id}/replanifier', name: 'terrain_replanifier', methods: ['POST'])]
    public function replanifier(Request $request, Operation $operation): Response
    {
        $this->denyAccessUnlessGranted('edit', $operation);

        if (!$this->isCsrfTokenValid('replanifier_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide.');
            return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
        }

        // Effacer le motif de report/notes si replanification
        $operation->setMotifReport(null);

        $success = $this->operationService->appliquerTransition($operation, 'replanifier');

        if ($success) {
            $this->addFlash('success', sprintf('Intervention %s replanifiee.', $operation->getDisplayIdentifier() ?? 'Operation'));
            return $this->redirectToRoute('terrain_index');
        }

        $this->addFlash('error', 'Impossible de replanifier cette intervention.');
        return $this->redirectToRoute('terrain_show', ['id' => $operation->getId()]);
    }

    /**
     * Recupere le champCible d'une etape depuis le mapping de la campagne.
     */
    private function getChampCibleForEtape(Operation $operation, string $etapeId): ?string
    {
        $campagne = $operation->getCampagne();
        if (!$campagne) {
            return null;
        }

        return $campagne->getChampCibleForEtape($etapeId);
    }
}
