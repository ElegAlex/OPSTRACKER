<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Repository\SegmentRepository;
use App\Repository\UtilisateurRepository;
use App\Service\OperationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        ]);
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

        if ($this->operationService->appliquerTransition($operation, $transition, $motif)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'statut' => $operation->getStatut(),
                    'statutLabel' => $operation->getStatutLabel(),
                    'statutCouleur' => $operation->getStatutCouleur(),
                    'transitions' => $this->operationService->getTransitionsDisponibles($operation),
                ]);
            }
            $this->addFlash('success', 'Statut de l\'operation mis a jour.');
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

        return $this->render('operation/show.html.twig', [
            'operation' => $operation,
            'campagne' => $campagne,
            'checklist' => $operation->getChecklistInstance(),
            'transitions' => $transitions,
            'techniciens' => $techniciens,
            'segments' => $segments,
        ]);
    }
}
