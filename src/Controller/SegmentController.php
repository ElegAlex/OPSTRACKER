<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Segment;
use App\Form\SegmentType;
use App\Repository\SegmentRepository;
use App\Service\OperationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la gestion des segments d'une campagne (vue Sophie).
 *
 * User Stories :
 * - US-905 : Creer/modifier des segments (T-405)
 * - US-906 : Voir la progression par segment (T-406)
 */
#[Route('/campagnes/{campagne}/segments')]
#[IsGranted('ROLE_USER')]
class SegmentController extends AbstractController
{
    public function __construct(
        private readonly OperationService $operationService,
        private readonly SegmentRepository $segmentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * T-405 / US-905 : Liste des segments + CRUD.
     * T-406 / US-906 : Progression par segment.
     */
    #[Route('', name: 'app_segment_index', methods: ['GET'])]
    public function index(Campagne $campagne): Response
    {
        $segments = $this->segmentRepository->findByCampagne($campagne->getId());
        $statistiques = $this->operationService->getStatistiquesParSegment($campagne);

        // Calculer le total des operations sans segment
        $operations = $campagne->getOperations();
        $sansSegment = [
            'total' => 0,
            'realises' => 0,
            'par_statut' => [],
        ];
        foreach ($operations as $operation) {
            if ($operation->getSegment() === null) {
                $sansSegment['total']++;
                $statut = $operation->getStatut();
                $sansSegment['par_statut'][$statut] = ($sansSegment['par_statut'][$statut] ?? 0) + 1;
                if ($statut === 'realise') {
                    $sansSegment['realises']++;
                }
            }
        }
        $sansSegment['progression'] = $sansSegment['total'] > 0
            ? round(($sansSegment['realises'] / $sansSegment['total']) * 100, 1)
            : 0;

        return $this->render('segment/index.html.twig', [
            'campagne' => $campagne,
            'segments' => $segments,
            'statistiques' => $statistiques,
            'sans_segment' => $sansSegment,
        ]);
    }

    /**
     * Creer un nouveau segment.
     */
    #[Route('/nouveau', name: 'app_segment_new', methods: ['GET', 'POST'])]
    public function new(Campagne $campagne, Request $request): Response
    {
        $segment = new Segment();
        $segment->setCampagne($campagne);

        $form = $this->createForm(SegmentType::class, $segment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Trouver l'ordre max actuel
            $segments = $campagne->getSegments();
            $maxOrdre = 0;
            foreach ($segments as $s) {
                if ($s->getOrdre() > $maxOrdre) {
                    $maxOrdre = $s->getOrdre();
                }
            }
            $segment->setOrdre($maxOrdre + 1);

            $this->entityManager->persist($segment);
            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'segment' => [
                        'id' => $segment->getId(),
                        'nom' => $segment->getNom(),
                        'couleur' => $segment->getCouleur(),
                    ],
                ]);
            }

            $this->addFlash('success', 'Segment cree avec succes.');
            return $this->redirectToRoute('app_segment_index', ['campagne' => $campagne->getId()]);
        }

        return $this->render('segment/new.html.twig', [
            'campagne' => $campagne,
            'segment' => $segment,
            'form' => $form,
        ]);
    }

    /**
     * T-406 / US-906 : Voir le detail d'un segment avec progression.
     */
    #[Route('/{id}', name: 'app_segment_show', methods: ['GET'])]
    public function show(Campagne $campagne, Segment $segment): Response
    {
        // Verifier que le segment appartient a la campagne
        if ($segment->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Segment non trouve dans cette campagne.');
        }

        $statistiques = $this->operationService->getStatistiquesSegment($segment);
        $operations = $segment->getOperations();

        // Preparer les transitions pour chaque operation
        $transitions = [];
        foreach ($operations as $operation) {
            $transitions[$operation->getId()] = $this->operationService->getTransitionsDisponibles($operation);
        }

        return $this->render('segment/show.html.twig', [
            'campagne' => $campagne,
            'segment' => $segment,
            'statistiques' => $statistiques,
            'operations' => $operations,
            'transitions' => $transitions,
        ]);
    }

    /**
     * Modifier un segment.
     */
    #[Route('/{id}/modifier', name: 'app_segment_edit', methods: ['GET', 'POST'])]
    public function edit(Campagne $campagne, Segment $segment, Request $request): Response
    {
        // Verifier que le segment appartient a la campagne
        if ($segment->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Segment non trouve dans cette campagne.');
        }

        $form = $this->createForm(SegmentType::class, $segment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'segment' => [
                        'id' => $segment->getId(),
                        'nom' => $segment->getNom(),
                        'couleur' => $segment->getCouleur(),
                    ],
                ]);
            }

            $this->addFlash('success', 'Segment modifie avec succes.');
            return $this->redirectToRoute('app_segment_index', ['campagne' => $campagne->getId()]);
        }

        return $this->render('segment/edit.html.twig', [
            'campagne' => $campagne,
            'segment' => $segment,
            'form' => $form,
        ]);
    }

    /**
     * Supprimer un segment.
     */
    #[Route('/{id}/supprimer', name: 'app_segment_delete', methods: ['POST'])]
    public function delete(Campagne $campagne, Segment $segment, Request $request): Response
    {
        // Verifier que le segment appartient a la campagne
        if ($segment->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Segment non trouve dans cette campagne.');
        }

        if (!$this->isCsrfTokenValid('delete_segment_' . $segment->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_segment_index', ['campagne' => $campagne->getId()]);
        }

        // Retirer les operations du segment avant suppression
        $this->operationService->supprimerSegment($segment);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Segment supprime. Les operations ont ete conservees sans segment.');
        return $this->redirectToRoute('app_segment_index', ['campagne' => $campagne->getId()]);
    }
}
