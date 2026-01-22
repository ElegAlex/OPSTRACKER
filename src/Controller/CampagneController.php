<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Form\CampagneStep1Type;
use App\Form\CampagneStep4Type;
use App\Form\OperationType;
use App\Repository\CampagneRepository;
use App\Service\CampagneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la gestion des campagnes (vue Sophie).
 *
 * User Stories :
 * - US-201 : Voir la liste des campagnes (T-301)
 * - US-202 : Creer campagne etape 1/4 (T-302)
 * - US-205 : Creer campagne etape 4/4 (T-303)
 * - US-206 : Ajouter une operation manuellement (T-304)
 */
#[Route('/campagnes')]
#[IsGranted('ROLE_USER')]
class CampagneController extends AbstractController
{
    public function __construct(
        private readonly CampagneService $campagneService,
        private readonly CampagneRepository $campagneRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * T-301 / US-201 : Liste des campagnes groupee par statut (portfolio).
     * RG-010 : 5 statuts avec couleurs distinctes
     */
    #[Route('', name: 'app_campagne_index', methods: ['GET'])]
    public function index(): Response
    {
        $campagnesGroupees = $this->campagneService->getCampagnesGroupedByStatut();
        $statistiques = $this->campagneService->getStatistiquesGlobales();

        // Calculer les stats par campagne pour l'affichage
        $statsParCampagne = [];
        foreach ($campagnesGroupees as $statut => $data) {
            foreach ($data['campagnes'] as $campagne) {
                $statsParCampagne[$campagne->getId()] = $this->campagneService->getStatistiquesCampagne($campagne);
            }
        }

        return $this->render('campagne/index.html.twig', [
            'campagnes_groupees' => $campagnesGroupees,
            'statistiques' => $statistiques,
            'stats_par_campagne' => $statsParCampagne,
        ]);
    }

    /**
     * T-302 / US-202 : Creer campagne - Etape 1/4 (Infos generales).
     * RG-011 : Nom + Dates obligatoires
     */
    #[Route('/nouvelle', name: 'app_campagne_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $campagne = new Campagne();

        $form = $this->createForm(CampagneStep1Type::class, $campagne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($campagne);
            $this->entityManager->flush();

            $this->addFlash('success', 'Campagne creee avec succes.');

            return $this->redirectToRoute('app_campagne_step4', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/new.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 1,
        ]);
    }

    /**
     * T-303 / US-205 : Creer campagne - Etape 4/4 (Workflow & Template).
     * RG-014 : Association TypeOperation + ChecklistTemplate
     */
    #[Route('/{id}/configurer', name: 'app_campagne_step4', methods: ['GET', 'POST'])]
    public function step4(Campagne $campagne, Request $request): Response
    {
        $form = $this->createForm(CampagneStep4Type::class, $campagne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Configuration de la campagne mise a jour.');

            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/step4.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 4,
        ]);
    }

    /**
     * Detail d'une campagne.
     */
    #[Route('/{id}', name: 'app_campagne_show', methods: ['GET'])]
    public function show(Campagne $campagne): Response
    {
        $statistiques = $this->campagneService->getStatistiquesCampagne($campagne);
        $transitions = $this->campagneService->getTransitionsDisponibles($campagne);

        return $this->render('campagne/show.html.twig', [
            'campagne' => $campagne,
            'statistiques' => $statistiques,
            'transitions' => $transitions,
        ]);
    }

    /**
     * T-304 / US-206 : Ajouter une operation manuellement.
     * RG-014 : Statut initial = "A planifier"
     * RG-015 : Donnees personnalisees JSONB
     */
    #[Route('/{id}/operations/nouvelle', name: 'app_campagne_operation_new', methods: ['GET', 'POST'])]
    public function newOperation(Campagne $campagne, Request $request): Response
    {
        $operation = new Operation();
        $operation->setCampagne($campagne);
        $operation->setTypeOperation($campagne->getTypeOperation());

        $form = $this->createForm(OperationType::class, $operation, [
            'campagne' => $campagne,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($operation);
            $this->entityManager->flush();

            $this->addFlash('success', 'Operation ajoutee avec succes.');

            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/operation_new.html.twig', [
            'campagne' => $campagne,
            'operation' => $operation,
            'form' => $form,
        ]);
    }

    /**
     * Applique une transition de workflow.
     */
    #[Route('/{id}/transition/{transition}', name: 'app_campagne_transition', methods: ['POST'])]
    public function transition(Campagne $campagne, string $transition, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('campagne_transition_' . $campagne->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        if ($this->campagneService->appliquerTransition($campagne, $transition)) {
            $this->addFlash('success', 'Statut de la campagne mis a jour.');
        } else {
            $this->addFlash('danger', 'Cette transition n\'est pas disponible.');
        }

        return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
    }

    /**
     * Supprime une campagne et toutes ses operations.
     */
    #[Route('/{id}/supprimer', name: 'app_campagne_delete', methods: ['POST'])]
    public function delete(Campagne $campagne, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('campagne_delete_' . $campagne->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_campagne_index');
        }

        $nom = $campagne->getNom();

        $this->entityManager->remove($campagne);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Campagne "%s" supprimee.', $nom));

        return $this->redirectToRoute('app_campagne_index');
    }
}
