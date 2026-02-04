<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Repository\DocumentRepository;
use App\Repository\SegmentRepository;
use App\Repository\UtilisateurRepository;
use App\Service\CampagneChampService;
use App\Service\ChecklistService;
use App\Service\OperationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use League\Csv\Writer;

/**
 * Controller pour la gestion des operations d'une campagne (vue Sophie).
 *
 * User Stories :
 * - US-301 : Voir la liste des operations (T-401)
 * - US-303 : Filtrer les operations (T-402)
 * - US-304 : Modifier le statut inline (T-403)
 * - US-306 : Assigner un technicien (T-404)
 */
#[Route('/campagnes/{campagne}/operations')]
#[IsGranted('ROLE_USER')]
class OperationController extends AbstractController
{
    public function __construct(
        private readonly OperationService $operationService,
        private readonly SegmentRepository $segmentRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly ChecklistService $checklistService,
        private readonly DocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * T-401 / US-301 : Liste des operations (vue tableau).
     * T-402 / US-303 : Filtres par statut, segment, technicien.
     * RG-080 : Triple signalisation (icone + couleur + texte)
     */
    #[Route('', name: 'app_operation_index', methods: ['GET'])]
    public function index(Campagne $campagne, Request $request): Response
    {
        // Recuperer les filtres de la requete
        $filtres = [
            'statut' => $request->query->get('statut'),
            'segment_id' => $request->query->get('segment') ? (int) $request->query->get('segment') : null,
            'technicien_id' => $request->query->get('technicien') ? (int) $request->query->get('technicien') : null,
            'search' => $request->query->get('search'),
        ];

        $operations = $this->operationService->getOperationsWithFilters($campagne, $filtres);
        $statistiques = $this->operationService->getStatistiquesParStatut($campagne);
        $segments = $this->segmentRepository->findByCampagne($campagne->getId());
        $techniciens = $this->utilisateurRepository->findTechniciensActifs();

        // Preparer les transitions disponibles pour chaque operation
        $transitions = [];
        foreach ($operations as $operation) {
            $transitions[$operation->getId()] = $this->operationService->getTransitionsDisponibles($operation);
        }

        return $this->render('operation/index.html.twig', [
            'campagne' => $campagne,
            'operations' => $operations,
            'statistiques' => $statistiques,
            'segments' => $segments,
            'techniciens' => $techniciens,
            'transitions' => $transitions,
            'filtres' => $filtres,
            'champs' => $campagne->getChamps(),
        ]);
    }

    /**
     * Export CSV des operations d'une campagne.
     * Exporte toutes les colonnes visibles dans le tableau.
     */
    #[Route('/export.csv', name: 'app_operation_export', methods: ['GET'])]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function exportCsv(Campagne $campagne): Response
    {
        $operations = $this->operationService->getOperationsWithFilters($campagne, []);
        $champs = $campagne->getChamps();

        $csv = Writer::createFromString();

        // BOM UTF-8 pour Excel Windows
        $csv->setOutputBOM(Writer::BOM_UTF8);

        // Construction des headers
        $headers = [];

        // Colonnes dynamiques (CampagneChamp) - exclure date/heure mappees
        foreach ($champs as $champ) {
            if ($champ->getNom() !== $campagne->getColonneDatePlanifiee()
                && $champ->getNom() !== $campagne->getColonneHoraire()) {
                $headers[] = $champ->getNom();
            }
        }

        // Colonne segment si definie
        if ($campagne->getColonneSegment()) {
            $headers[] = $campagne->getColonneSegment();
        }

        // Colonnes fixes
        $headers[] = 'Statut';
        $headers[] = 'Checklist';
        $headers[] = 'Technicien';
        $headers[] = 'Date';
        $headers[] = 'Heure';

        // Colonne duree si activee
        $showDuree = $campagne->isSaisieTempsActivee();
        if ($showDuree) {
            $headers[] = 'Duree';
        }

        // Colonne reservation si ouverte
        if ($campagne->isReservationOuverte()) {
            if ($campagne->isMultiPlaces()) {
                $headers[] = 'Places reservees';
                $headers[] = 'Capacite';
            } else {
                $headers[] = 'Reserve par';
            }
        }

        $csv->insertOne($headers);

        // Donnees
        $totalEtapes = $campagne->getNombreEtapesActives();

        foreach ($operations as $operation) {
            $row = [];

            // Colonnes dynamiques
            foreach ($champs as $champ) {
                if ($champ->getNom() !== $campagne->getColonneDatePlanifiee()
                    && $champ->getNom() !== $campagne->getColonneHoraire()) {
                    $row[] = $operation->getDonneePersonnalisee($champ->getNom()) ?? '-';
                }
            }

            // Segment
            if ($campagne->getColonneSegment()) {
                $donneesPerso = $operation->getDonneesPersonnalisees() ?? [];
                $row[] = $donneesPerso[$campagne->getColonneSegment()] ?? '-';
            }

            // Statut
            $row[] = $operation->getStatutLabel();

            // Checklist
            if ($totalEtapes > 0) {
                $instance = $operation->getChecklistInstance();
                $completed = $instance ? $instance->getNombreEtapesCochees() : 0;
                $row[] = $completed . '/' . $totalEtapes;
            } else {
                $row[] = '-';
            }

            // Technicien
            $row[] = $operation->getTechnicienAssigne()?->getNomComplet() ?? 'Non assigne';

            // Date et Heure
            $datePlanifiee = $operation->getDatePlanifiee();
            $row[] = $datePlanifiee?->format('d/m/Y') ?? '-';
            $row[] = $datePlanifiee?->format('H:i') ?? '-';

            // Duree
            if ($showDuree) {
                $row[] = $operation->getDureeFormatee() ?? '-';
            }

            // Reservation
            if ($campagne->isReservationOuverte()) {
                if ($campagne->isMultiPlaces()) {
                    $row[] = $operation->getPlacesReservees();
                    $row[] = $operation->getCapacite();
                } else {
                    $reservations = $operation->getReservationsEndUser();
                    if (count($reservations) > 0) {
                        $first = $reservations->first();
                        $row[] = $first ? $first->getNomPrenom() : '-';
                    } else {
                        $row[] = '-';
                    }
                }
            }

            $csv->insertOne($row);
        }

        $filename = sprintf(
            'operations_campagne_%d_%s.csv',
            $campagne->getId(),
            (new \DateTime())->format('Y-m-d')
        );

        $response = new Response($csv->toString());
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * T-403 / US-304 : Modifier le statut d'une operation (inline).
     * RG-017 : Transitions validees par le workflow Symfony
     * RG-080 : Triple signalisation
     */
    #[Route('/{id}/transition/{transition}', name: 'app_operation_transition', methods: ['POST'])]
    public function transition(
        Campagne $campagne,
        Operation $operation,
        string $transition,
        Request $request
    ): Response {
        // Verifier que l'operation appartient a la campagne
        if ($operation->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee dans cette campagne.');
        }

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('operation_transition_' . $operation->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
        }

        // Recuperer le motif de report si present
        $motif = $request->request->get('motif');

        // Gestion speciale pour le report avec date optionnelle
        $nouvelleDatePlanifiee = null;

        if ($transition === 'reporter') {
            $dateStr = $request->request->get('nouvelle_date');
            $heureStr = $request->request->get('nouvelle_heure', '09:00');

            if ($dateStr !== null && $dateStr !== '') {
                try {
                    $datetime = $dateStr . ' ' . ($heureStr ?: '09:00');
                    $nouvelleDatePlanifiee = new \DateTimeImmutable($datetime);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Format de date invalide.');
                    return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
                }
            }
            // Sans date -> nouvelleDatePlanifiee reste null, statut passe a reporte sans date
        }

        if ($this->operationService->appliquerTransition($operation, $transition, $motif, $nouvelleDatePlanifiee)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'statut' => $operation->getStatut(),
                    'statutLabel' => $operation->getStatutLabel(),
                    'statutCouleur' => $operation->getStatutCouleur(),
                    'transitions' => $this->operationService->getTransitionsDisponibles($operation),
                ]);
            }

            // Message adapte selon le type de transition
            if ($transition === 'reporter' && $nouvelleDatePlanifiee !== null) {
                $this->addFlash('warning', sprintf(
                    'Operation reportee au %s.',
                    $nouvelleDatePlanifiee->format('d/m/Y H:i')
                ));
            } elseif ($transition === 'reporter') {
                $this->addFlash('warning', 'Operation reportee (sans nouvelle date).');
            } else {
                $this->addFlash('success', 'Statut de l\'operation mis a jour.');
            }
        } else {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Cette transition n\'est pas disponible.'], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('danger', 'Cette transition n\'est pas disponible.');
        }

        return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
    }

    /**
     * T-404 / US-306 : Assigner un technicien a une operation.
     * RG-018 : 1 operation = 1 technicien assigne maximum
     */
    #[Route('/{id}/assigner', name: 'app_operation_assigner', methods: ['POST'])]
    public function assigner(
        Campagne $campagne,
        Operation $operation,
        Request $request
    ): Response {
        // Verifier que l'operation appartient a la campagne
        if ($operation->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee dans cette campagne.');
        }

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('operation_assigner_' . $operation->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
        }

        $technicienId = $request->request->get('technicien_id');
        $technicien = null;

        if ($technicienId !== null && $technicienId !== '' && $technicienId !== '0') {
            $technicien = $this->utilisateurRepository->find((int) $technicienId);
            if ($technicien === null) {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['error' => 'Technicien non trouve.'], Response::HTTP_NOT_FOUND);
                }
                $this->addFlash('danger', 'Technicien non trouve.');
                return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
            }
        }

        try {
            $this->operationService->assignerTechnicien($operation, $technicien);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'technicien' => $technicien ? [
                        'id' => $technicien->getId(),
                        'nom' => $technicien->getNomComplet(),
                        'initiales' => strtoupper(substr($technicien->getPrenom(), 0, 1) . substr($technicien->getNom(), 0, 1)),
                    ] : null,
                ]);
            }

            $this->addFlash('success', $technicien
                ? 'Technicien assigne avec succes.'
                : 'Technicien desassigne.'
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
    }

    /**
     * Assigne une operation a un segment.
     */
    #[Route('/{id}/segment', name: 'app_operation_segment', methods: ['POST'])]
    public function assignerSegment(
        Campagne $campagne,
        Operation $operation,
        Request $request
    ): Response {
        // Verifier que l'operation appartient a la campagne
        if ($operation->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee dans cette campagne.');
        }

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('operation_segment_' . $operation->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
        }

        $segmentId = $request->request->get('segment_id');
        $segment = null;

        if ($segmentId !== null && $segmentId !== '' && $segmentId !== '0') {
            $segment = $this->segmentRepository->find((int) $segmentId);
            if ($segment === null || $segment->getCampagne()->getId() !== $campagne->getId()) {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['error' => 'Segment non trouve.'], Response::HTTP_NOT_FOUND);
                }
                $this->addFlash('danger', 'Segment non trouve.');
                return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
            }
        }

        $this->operationService->assignerSegment($operation, $segment);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'segment' => $segment ? [
                    'id' => $segment->getId(),
                    'nom' => $segment->getNom(),
                    'couleur' => $segment->getCouleur(),
                ] : null,
            ]);
        }

        $this->addFlash('success', $segment
            ? 'Segment assigne avec succes.'
            : 'Operation retiree du segment.'
        );

        return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
    }

    /**
     * US-306 : Modifier une operation.
     * Permet de modifier les informations de base d'une operation.
     */
    #[Route('/{id}/modifier', name: 'app_operation_edit', methods: ['GET', 'POST'])]
    public function edit(
        Campagne $campagne,
        Operation $operation,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verifier que l'operation appartient a la campagne
        if ($operation->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee dans cette campagne.');
        }

        $form = $this->createForm(\App\Form\OperationType::class, $operation, [
            'campagne' => $campagne,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traiter les champs personnalises (CampagneChamp)
            $donneesPersonnalisees = $operation->getDonneesPersonnalisees() ?? [];
            foreach ($campagne->getChamps() as $champ) {
                $champNom = $champ->getNom();

                // Ignorer les champs natifs
                if (CampagneChampService::isNativeField($champNom)) {
                    continue;
                }

                $fieldName = CampagneChampService::normalizeFieldName($champNom);

                if ($form->has($fieldName)) {
                    $valeur = $form->get($fieldName)->getData();
                    if ($valeur !== null && $valeur !== '') {
                        $donneesPersonnalisees[$champNom] = $valeur;
                    } else {
                        unset($donneesPersonnalisees[$champNom]);
                    }
                }
            }
            $operation->setDonneesPersonnalisees(!empty($donneesPersonnalisees) ? $donneesPersonnalisees : null);

            $entityManager->flush();

            $this->addFlash('success', 'Operation modifiee avec succes.');

            return $this->redirectToRoute('app_operation_show', [
                'campagne' => $campagne->getId(),
                'id' => $operation->getId(),
            ]);
        }

        return $this->render('operation/edit.html.twig', [
            'operation' => $operation,
            'campagne' => $campagne,
            'form' => $form,
            'champs' => $campagne->getChamps(),
        ]);
    }

    /**
     * Modifier le statut d'une operation directement (sans workflow).
     * Permet de passer a n'importe quel statut valide.
     */
    #[Route('/{id}/statut', name: 'app_operation_update_statut', methods: ['POST'])]
    public function updateStatut(
        Campagne $campagne,
        Operation $operation,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verifier que l'operation appartient a la campagne
        if ($operation->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee dans cette campagne.');
        }

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('operation_statut_' . $operation->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
        }

        $newStatut = $request->request->get('statut');

        // Liste des statuts valides
        $validStatuts = array_keys(Operation::STATUTS);

        if (in_array($newStatut, $validStatuts, true)) {
            $operation->setStatut($newStatut);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'statut' => $operation->getStatut(),
                    'statutLabel' => $operation->getStatutLabel(),
                    'statutCouleur' => $operation->getStatutCouleur(),
                ]);
            }

            $this->addFlash('success', 'Statut mis a jour.');
        } else {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Statut invalide.'], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('danger', 'Statut invalide.');
        }

        return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
    }

    /**
     * Modifier la date planifiee d'une operation (inline).
     */
    #[Route('/{id}/date', name: 'app_operation_update_date', methods: ['POST'])]
    public function updateDate(
        Campagne $campagne,
        Operation $operation,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verifier que l'operation appartient a la campagne
        if ($operation->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee dans cette campagne.');
        }

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('operation_date_' . $operation->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
        }

        $dateStr = $request->request->get('date_planifiee');

        if ($dateStr !== null && $dateStr !== '') {
            try {
                $date = new \DateTimeImmutable($dateStr);
                $operation->setDatePlanifiee($date);
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'date' => $date->format('d/m/Y H:i'),
                    ]);
                }

                $this->addFlash('success', 'Date planifiee mise a jour.');
            } catch (\Exception $e) {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['error' => 'Format de date invalide.'], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('danger', 'Format de date invalide.');
            }
        } else {
            // Permet d'effacer la date
            $operation->setDatePlanifiee(null);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'date' => null,
                ]);
            }

            $this->addFlash('success', 'Date planifiee supprimee.');
        }

        return $this->redirectToRoute('app_operation_index', ['campagne' => $campagne->getId()]);
    }

    /**
     * US-305 : Voir le detail d'une operation.
     * Affiche toutes les informations de l'operation.
     */
    #[Route('/{id}', name: 'app_operation_show', methods: ['GET'])]
    public function show(Campagne $campagne, Operation $operation): Response
    {
        // Verifier que l'operation appartient a la campagne
        if ($operation->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee dans cette campagne.');
        }

        $transitions = $this->operationService->getTransitionsDisponibles($operation);
        $techniciens = $this->utilisateurRepository->findTechniciensActifs();
        $segments = $this->segmentRepository->findByCampagne($campagne->getId());

        // Calculer la progression de la checklist si elle existe
        $checklistProgression = null;
        $documentsById = [];

        // Nouvelle architecture : vérifier si la campagne a une structure de checklist
        if ($campagne->hasChecklistStructure()) {
            // Créer l'instance si elle n'existe pas encore
            $instance = $operation->getChecklistInstance();
            if (!$instance) {
                $instance = $this->checklistService->creerInstancePourOperation($operation);
            }

            if ($instance) {
                $checklistProgression = $this->checklistService->getProgression($instance);
            }

            // Charger les documents de la campagne pour la checklist
            $documents = $this->documentRepository->findByCampagne($campagne->getId());
            foreach ($documents as $doc) {
                $documentsById[$doc->getId()] = $doc;
            }
        } elseif ($operation->getChecklistInstance()) {
            // Fallback : ancienne architecture avec snapshot
            $checklistProgression = $this->checklistService->getProgression($operation->getChecklistInstance());

            $documents = $this->documentRepository->findByCampagne($campagne->getId());
            foreach ($documents as $doc) {
                $documentsById[$doc->getId()] = $doc;
            }
        }

        return $this->render('operation/show.html.twig', [
            'operation' => $operation,
            'campagne' => $campagne,
            'checklist' => $operation->getChecklistInstance(),
            'checklist_progression' => $checklistProgression,
            'documents' => $documentsById,
            'transitions' => $transitions,
            'techniciens' => $techniciens,
            'segments' => $segments,
            'champs' => $campagne->getChamps(),
        ]);
    }

    /**
     * Toggle (coche/decoche) une etape de la checklist.
     * Accessible aux gestionnaires et admins (en plus des techniciens via terrain).
     */
    #[Route('/{id}/checklist/toggle/{etapeId}', name: 'app_operation_checklist_toggle', methods: ['POST'])]
    public function toggleChecklistEtape(
        Campagne $campagne,
        Operation $operation,
        string $etapeId,
        Request $request
    ): Response {
        // Verifier que l'operation appartient a la campagne
        if ($operation->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee dans cette campagne.');
        }

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('checklist_' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de securite invalide.');
            return $this->redirectToRoute('app_operation_show', [
                'campagne' => $campagne->getId(),
                'id' => $operation->getId(),
            ]);
        }

        $instance = $operation->getChecklistInstance();
        if (!$instance) {
            $this->addFlash('danger', 'Pas de checklist pour cette operation.');
            return $this->redirectToRoute('app_operation_show', [
                'campagne' => $campagne->getId(),
                'id' => $operation->getId(),
            ]);
        }

        try {
            $this->checklistService->toggleEtape($instance, $etapeId, $this->getUser());

            // Traitement champ de saisie si present
            $valeurChamp = $request->request->get('valeur_champ');
            if ($valeurChamp !== null) {
                $champCible = $this->getChampCibleForEtape($operation, $etapeId);
                if ($champCible) {
                    $valeur = trim($valeurChamp);
                    if ($valeur !== '') {
                        $operation->setDonneePersonnalisee($champCible, $valeur);
                        $this->entityManager->flush();
                    }
                }
            }
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        // Calculer la nouvelle progression
        $progression = $this->checklistService->getProgression($instance);

        // Charger les documents pour la checklist
        $documents = $this->documentRepository->findByCampagne($campagne->getId());
        $documentsById = [];
        foreach ($documents as $doc) {
            $documentsById[$doc->getId()] = $doc;
        }

        // Si requete Turbo, retourner uniquement le fragment
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('operation/_checklist.html.twig', [
                'operation' => $operation,
                'campagne' => $campagne,
                'checklist_progression' => $progression,
                'documents' => $documentsById,
            ]);
        }

        return $this->redirectToRoute('app_operation_show', [
            'campagne' => $campagne->getId(),
            'id' => $operation->getId(),
        ]);
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
