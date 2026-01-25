<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Prerequis;
use App\Entity\Segment;
use App\Form\PrerequisType;
use App\Repository\PrerequisRepository;
use App\Repository\CampagneRepository;
use App\Repository\SegmentRepository;
use App\Service\PrerequisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la gestion des prerequis de campagne.
 *
 * User Stories implementees :
 * - US-901 : Voir les prerequis globaux d'une campagne (T-1301)
 * - US-902 : Ajouter/modifier un prerequis global (T-1302)
 * - US-903 : Voir les prerequis par segment (T-1303)
 * - US-904 : Ajouter un prerequis par segment (T-1304)
 *
 * Regles metier :
 * - RG-090 : Prerequis globaux de campagne (indicateur declaratif, NON bloquant)
 * - RG-091 : Prerequis specifiques a un segment (indicateur declaratif, NON bloquant)
 */
#[Route('/campagne/{campagneId}/prerequis')]
#[IsGranted('ROLE_USER')]
class PrerequisController extends AbstractController
{
    public function __construct(
        private readonly PrerequisService $prerequisService,
        private readonly PrerequisRepository $prerequisRepository,
        private readonly SegmentRepository $segmentRepository,
        private readonly CampagneRepository $campagneRepository,
    ) {
    }

    /**
     * T-1301 + T-1303 : Affiche l'onglet prerequis d'une campagne
     * Liste les prerequis globaux et par segment
     */
    #[Route('', name: 'app_prerequis_index', methods: ['GET'])]
    public function index(int $campagneId, Request $request): Response
    {
        $campagne = $this->getCampagne($campagneId);
        $data = $this->prerequisService->getDonneesOngletPrerequis($campagne);

        return $this->render('prerequis/index.html.twig', [
            'campagne' => $campagne,
            'data' => $data,
        ]);
    }

    /**
     * T-1302 : Ajouter un prerequis global
     */
    #[Route('/global/new', name: 'app_prerequis_global_new', methods: ['GET', 'POST'])]
    public function newGlobal(int $campagneId, Request $request): Response
    {
        $campagne = $this->getCampagne($campagneId);
        $this->checkNotReadOnly($campagne);

        $prerequis = new Prerequis();
        $prerequis->setCampagne($campagne);

        $form = $this->createForm(PrerequisType::class, $prerequis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $prerequis->setOrdre($this->prerequisRepository->getNextOrdre($campagne));

            $this->prerequisRepository->save($prerequis, true);

            $this->addFlash('success', 'Prerequis global ajoute avec succes.');

            return $this->redirectToRoute('app_prerequis_index', ['campagneId' => $campagneId]);
        }

        return $this->render('prerequis/new_global.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
        ]);
    }

    /**
     * T-1304 : Ajouter un prerequis pour un segment
     */
    #[Route('/segment/{segmentId}/new', name: 'app_prerequis_segment_new', methods: ['GET', 'POST'])]
    public function newSegment(int $campagneId, int $segmentId, Request $request): Response
    {
        $campagne = $this->getCampagne($campagneId);
        $this->checkNotReadOnly($campagne);

        $segment = $this->segmentRepository->find($segmentId);
        if (!$segment || $segment->getCampagne()->getId() !== $campagneId) {
            throw $this->createNotFoundException('Segment non trouve');
        }

        $prerequis = new Prerequis();
        $prerequis->setCampagne($campagne);
        $prerequis->setSegment($segment);

        $form = $this->createForm(PrerequisType::class, $prerequis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $prerequis->setOrdre($this->prerequisRepository->getNextOrdre($campagne, $segment));

            $this->prerequisRepository->save($prerequis, true);

            $this->addFlash('success', sprintf('Prerequis ajoute pour %s.', $segment->getNom()));

            return $this->redirectToRoute('app_prerequis_index', ['campagneId' => $campagneId]);
        }

        return $this->render('prerequis/new_segment.html.twig', [
            'campagne' => $campagne,
            'segment' => $segment,
            'form' => $form,
        ]);
    }

    /**
     * T-1302 : Modifier un prerequis
     */
    #[Route('/{id}/edit', name: 'app_prerequis_edit', methods: ['GET', 'POST'])]
    public function edit(int $campagneId, Prerequis $prerequis, Request $request): Response
    {
        $campagne = $this->getCampagne($campagneId);
        $this->checkNotReadOnly($campagne);
        $this->checkPrerequisBelongsToCampagne($prerequis, $campagne);

        $form = $this->createForm(PrerequisType::class, $prerequis, ['include_statut' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->prerequisRepository->save($prerequis, true);

            $this->addFlash('success', 'Prerequis modifie avec succes.');

            return $this->redirectToRoute('app_prerequis_index', ['campagneId' => $campagneId]);
        }

        return $this->render('prerequis/edit.html.twig', [
            'campagne' => $campagne,
            'prerequis' => $prerequis,
            'form' => $form,
        ]);
    }

    /**
     * Changer le statut d'un prerequis (inline via Turbo)
     */
    #[Route('/{id}/statut', name: 'app_prerequis_statut', methods: ['POST'])]
    public function changeStatut(int $campagneId, Prerequis $prerequis, Request $request): Response
    {
        $campagne = $this->getCampagne($campagneId);
        $this->checkNotReadOnly($campagne);
        $this->checkPrerequisBelongsToCampagne($prerequis, $campagne);

        $nouveauStatut = $request->request->get('statut');
        if (!array_key_exists($nouveauStatut, Prerequis::STATUTS)) {
            throw $this->createNotFoundException('Statut invalide');
        }

        $this->prerequisService->updateStatut($prerequis, $nouveauStatut);

        // Retourner un Turbo Stream pour mise a jour inline
        if ($request->headers->get('Accept') === 'text/vnd.turbo-stream.html') {
            return $this->render('prerequis/_turbo_statut.html.twig', [
                'prerequis' => $prerequis,
                'campagne' => $campagne,
            ], new Response('', 200, ['Content-Type' => 'text/vnd.turbo-stream.html']));
        }

        // Retourner le partial _row wrappe dans le turbo-frame pour mise a jour inline
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('prerequis/_row_frame.html.twig', [
                'prerequis' => $prerequis,
                'campagne' => $campagne,
            ]);
        }

        return $this->redirectToRoute('app_prerequis_index', ['campagneId' => $campagneId]);
    }

    /**
     * Supprimer un prerequis
     */
    #[Route('/{id}/delete', name: 'app_prerequis_delete', methods: ['POST'])]
    public function delete(int $campagneId, Prerequis $prerequis, Request $request): Response
    {
        $campagne = $this->getCampagne($campagneId);
        $this->checkNotReadOnly($campagne);
        $this->checkPrerequisBelongsToCampagne($prerequis, $campagne);

        if ($this->isCsrfTokenValid('delete' . $prerequis->getId(), $request->request->get('_token'))) {
            $this->prerequisService->supprimer($prerequis);
            $this->addFlash('success', 'Prerequis supprime.');
        }

        return $this->redirectToRoute('app_prerequis_index', ['campagneId' => $campagneId]);
    }

    /**
     * Turbo Frame : Ligne de prerequis mise a jour
     */
    #[Route('/{id}/row', name: 'app_prerequis_row', methods: ['GET'])]
    public function row(int $campagneId, Prerequis $prerequis): Response
    {
        $campagne = $this->getCampagne($campagneId);
        $this->checkPrerequisBelongsToCampagne($prerequis, $campagne);

        return $this->render('prerequis/_row.html.twig', [
            'prerequis' => $prerequis,
            'campagne' => $campagne,
        ]);
    }

    private function getCampagne(int $campagneId): Campagne
    {
        $campagne = $this->campagneRepository->find($campagneId);

        if (!$campagne) {
            throw $this->createNotFoundException('Campagne non trouvee');
        }

        return $campagne;
    }

    private function checkNotReadOnly(Campagne $campagne): void
    {
        if ($campagne->isReadOnly()) {
            throw $this->createAccessDeniedException('La campagne est en lecture seule (archivee)');
        }
    }

    private function checkPrerequisBelongsToCampagne(Prerequis $prerequis, Campagne $campagne): void
    {
        if ($prerequis->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Prerequis non trouve dans cette campagne');
        }
    }
}
