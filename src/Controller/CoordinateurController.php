<?php

namespace App\Controller;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\AgentRepository;
use App\Repository\CoordinateurPerimetreRepository;
use App\Repository\CreneauRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour l'interface coordinateur (positionnement agents delegues).
 *
 * User Stories :
 * - US-1010 : Interface coordinateur (T-2003)
 *
 * Regles metier :
 * - RG-114 : Coordinateur peut positionner des agents sans lien hierarchique
 * - RG-121 : Un agent = un seul creneau par campagne
 * - RG-123 : Verrouillage J-X
 * - RG-125 : Tracabilite : enregistrer qui a positionne
 */
#[Route('/coordinateur/campagne/{campagne}')]
#[IsGranted('ROLE_COORDINATEUR')]
class CoordinateurController extends AbstractController
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly CoordinateurPerimetreRepository $perimetreRepository,
        private readonly CreneauRepository $creneauRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationService $reservationService,
    ) {
    }

    /**
     * T-2003 / US-1010 : Liste des agents dans le perimetre du coordinateur.
     * RG-114 : Filtrer par services delegues.
     */
    #[Route('/agents', name: 'app_coord_agents', methods: ['GET'])]
    public function agents(Campagne $campagne): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $services = $this->perimetreRepository->findServicesForCoordinateur($user);

        if (empty($services)) {
            $this->addFlash('warning', 'Aucun service n\'est delegue a votre compte coordinateur.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        // Recuperer les agents des services delegues
        $agents = [];
        foreach ($services as $service) {
            $agentsService = $this->agentRepository->findByService($service);
            $agents = array_merge($agents, $agentsService);
        }

        // Trier par nom
        usort($agents, fn (Agent $a, Agent $b) => $a->getNom() <=> $b->getNom());

        // Recuperer les reservations existantes
        $reservationsParAgent = [];
        foreach ($agents as $agent) {
            $reservation = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);
            $reservationsParAgent[(int) $agent->getId()] = $reservation;
        }

        // Statistiques
        $totalAgents = count($agents);
        $agentsPositionnes = count(array_filter($reservationsParAgent, fn ($r) => $r !== null));

        return $this->render('coordinateur/agents.html.twig', [
            'campagne' => $campagne,
            'agents' => $agents,
            'services' => $services,
            'reservations' => $reservationsParAgent,
            'total_agents' => $totalAgents,
            'agents_positionnes' => $agentsPositionnes,
        ]);
    }

    /**
     * T-2003 / US-1010 : Positionner un agent du perimetre delegue.
     * RG-114, RG-121, RG-125 : Delegation, unicite, tracabilite.
     */
    #[Route('/positionner/{agent}', name: 'app_coord_position', methods: ['GET', 'POST'])]
    public function position(Campagne $campagne, Agent $agent, Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        // RG-114 : Verifier que l'agent est dans le perimetre du coordinateur
        $agentService = $agent->getService();
        if ($agentService === null || !$this->perimetreRepository->hasAccessToServiceAndSite($user, $agentService, $agent->getSite())) {
            throw $this->createAccessDeniedException('Cet agent n\'est pas dans votre perimetre.');
        }

        // RG-121 : Verifier qu'il n'a pas deja une reservation
        $reservationExistante = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);
        if ($reservationExistante) {
            $this->addFlash('warning', sprintf(
                '%s a deja une reservation pour cette campagne.',
                $agent->getNomComplet()
            ));

            return $this->redirectToRoute('app_coord_agents', ['campagne' => $campagne->getId()]);
        }

        // POST : Traiter le positionnement
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('coord_position_' . (int) $agent->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');

                return $this->redirectToRoute('app_coord_position', [
                    'campagne' => $campagne->getId(),
                    'agent' => $agent->getId(),
                ]);
            }

            $creneauId = $request->request->get('creneau');
            if (!$creneauId) {
                $this->addFlash('danger', 'Veuillez selectionner un creneau.');

                return $this->redirectToRoute('app_coord_position', [
                    'campagne' => $campagne->getId(),
                    'agent' => $agent->getId(),
                ]);
            }

            $creneau = $this->creneauRepository->find($creneauId);
            if (!$creneau || $creneau->getCampagne()?->getId() !== $campagne->getId()) {
                $this->addFlash('danger', 'Creneau invalide.');

                return $this->redirectToRoute('app_coord_position', [
                    'campagne' => $campagne->getId(),
                    'agent' => $agent->getId(),
                ]);
            }

            try {
                // RG-125 : Positionnement par coordinateur avec tracabilite
                $reservation = $this->reservationService->reserver(
                    $agent,
                    $creneau,
                    Reservation::TYPE_COORDINATEUR,
                    $user
                );

                $creneauDate = $creneau->getDate();
                $creneauHeureDebut = $creneau->getHeureDebut();
                $creneauHeureFin = $creneau->getHeureFin();
                $this->addFlash('success', sprintf(
                    '%s a ete positionne(e) le %s de %s a %s.',
                    $agent->getNomComplet(),
                    $creneauDate !== null ? $creneauDate->format('d/m/Y') : '?',
                    $creneauHeureDebut !== null ? $creneauHeureDebut->format('H:i') : '?',
                    $creneauHeureFin !== null ? $creneauHeureFin->format('H:i') : '?'
                ));

                return $this->redirectToRoute('app_coord_agents', ['campagne' => $campagne->getId()]);
            } catch (\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('app_coord_position', [
                    'campagne' => $campagne->getId(),
                    'agent' => $agent->getId(),
                ]);
            }
        }

        // GET : Afficher les creneaux disponibles
        $creneauxDisponibles = $this->creneauRepository->findDisponibles($campagne);

        // Filtrer les creneaux verrouilles
        $creneauxParDate = [];
        foreach ($creneauxDisponibles as $creneau) {
            $creneauDate = $creneau->getDate();
            if ($creneauDate === null || $creneau->isVerrouillePourDate()) {
                continue;
            }

            $dateKey = $creneauDate->format('Y-m-d');
            if (!isset($creneauxParDate[$dateKey])) {
                $creneauxParDate[$dateKey] = [
                    'date' => $creneauDate,
                    'creneaux' => [],
                ];
            }
            $creneauxParDate[$dateKey]['creneaux'][] = $creneau;
        }
        ksort($creneauxParDate);

        return $this->render('coordinateur/position.html.twig', [
            'campagne' => $campagne,
            'agent' => $agent,
            'creneaux_par_date' => $creneauxParDate,
        ]);
    }

    /**
     * Modifier la reservation d'un agent du perimetre.
     * RG-114, RG-123 : Delegation et verrouillage.
     */
    #[Route('/modifier/{reservation}', name: 'app_coord_modify', methods: ['GET', 'POST'])]
    public function modify(Campagne $campagne, Reservation $reservation, Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        // Verifier que la reservation concerne la bonne campagne
        if ($reservation->getCampagne()?->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Reservation non trouvee dans cette campagne.');
        }

        $agent = $reservation->getAgent();
        if ($agent === null) {
            throw $this->createNotFoundException('Agent non trouve pour cette reservation.');
        }

        // RG-114 : Verifier le perimetre
        $agentService = $agent->getService();
        if ($agentService === null || !$this->perimetreRepository->hasAccessToServiceAndSite($user, $agentService, $agent->getSite())) {
            throw $this->createAccessDeniedException('Cet agent n\'est pas dans votre perimetre.');
        }

        // RG-123 : Verifier le verrouillage
        $creneauActuel = $reservation->getCreneau();
        if ($creneauActuel === null) {
            throw $this->createNotFoundException('Creneau non trouve pour cette reservation.');
        }
        if ($creneauActuel->isVerrouillePourDate()) {
            $this->addFlash('danger', 'Ce creneau est verrouille et ne peut plus etre modifie.');

            return $this->redirectToRoute('app_coord_agents', ['campagne' => $campagne->getId()]);
        }

        // POST : Traiter la modification
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('coord_modify_' . (int) $reservation->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');

                return $this->redirectToRoute('app_coord_modify', [
                    'campagne' => $campagne->getId(),
                    'reservation' => $reservation->getId(),
                ]);
            }

            $nouveauCreneauId = $request->request->get('creneau');
            if (!$nouveauCreneauId) {
                $this->addFlash('danger', 'Veuillez selectionner un nouveau creneau.');

                return $this->redirectToRoute('app_coord_modify', [
                    'campagne' => $campagne->getId(),
                    'reservation' => $reservation->getId(),
                ]);
            }

            $nouveauCreneau = $this->creneauRepository->find($nouveauCreneauId);
            if (!$nouveauCreneau || $nouveauCreneau->getCampagne()?->getId() !== $campagne->getId()) {
                $this->addFlash('danger', 'Creneau invalide.');

                return $this->redirectToRoute('app_coord_modify', [
                    'campagne' => $campagne->getId(),
                    'reservation' => $reservation->getId(),
                ]);
            }

            try {
                $this->reservationService->modifier($reservation, $nouveauCreneau);

                $this->addFlash('success', sprintf(
                    'Le creneau de %s a ete modifie.',
                    $agent->getNomComplet()
                ));

                return $this->redirectToRoute('app_coord_agents', ['campagne' => $campagne->getId()]);
            } catch (\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('app_coord_modify', [
                    'campagne' => $campagne->getId(),
                    'reservation' => $reservation->getId(),
                ]);
            }
        }

        // GET : Afficher les creneaux disponibles
        $creneauxDisponibles = $this->creneauRepository->findDisponibles($campagne);

        $creneauxParDate = [];
        foreach ($creneauxDisponibles as $creneau) {
            $creneauDate = $creneau->getDate();
            if ($creneauDate === null || $creneau->isVerrouillePourDate() || $creneau->getId() === $creneauActuel->getId()) {
                continue;
            }

            $dateKey = $creneauDate->format('Y-m-d');
            if (!isset($creneauxParDate[$dateKey])) {
                $creneauxParDate[$dateKey] = [
                    'date' => $creneauDate,
                    'creneaux' => [],
                ];
            }
            $creneauxParDate[$dateKey]['creneaux'][] = $creneau;
        }
        ksort($creneauxParDate);

        return $this->render('coordinateur/modify.html.twig', [
            'campagne' => $campagne,
            'agent' => $agent,
            'reservation' => $reservation,
            'creneaux_par_date' => $creneauxParDate,
        ]);
    }

    /**
     * Annuler la reservation d'un agent du perimetre.
     */
    #[Route('/annuler/{reservation}', name: 'app_coord_cancel', methods: ['POST'])]
    public function cancel(Campagne $campagne, Reservation $reservation, Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if ($reservation->getCampagne()?->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Reservation non trouvee dans cette campagne.');
        }

        $agent = $reservation->getAgent();
        if ($agent === null) {
            throw $this->createNotFoundException('Agent non trouve pour cette reservation.');
        }

        // RG-114 : Verifier le perimetre
        $agentService = $agent->getService();
        if ($agentService === null || !$this->perimetreRepository->hasAccessToServiceAndSite($user, $agentService, $agent->getSite())) {
            throw $this->createAccessDeniedException('Cet agent n\'est pas dans votre perimetre.');
        }

        if (!$this->isCsrfTokenValid('coord_cancel_' . (int) $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_coord_agents', ['campagne' => $campagne->getId()]);
        }

        // RG-123 : Verifier le verrouillage
        $creneau = $reservation->getCreneau();
        if ($creneau === null) {
            throw $this->createNotFoundException('Creneau non trouve pour cette reservation.');
        }
        if ($creneau->isVerrouillePourDate()) {
            $this->addFlash('danger', 'Ce creneau est verrouille et ne peut plus etre annule.');

            return $this->redirectToRoute('app_coord_agents', ['campagne' => $campagne->getId()]);
        }

        $this->reservationService->annuler($reservation);

        $this->addFlash('success', sprintf(
            'La reservation de %s a ete annulee.',
            $agent->getNomComplet()
        ));

        return $this->redirectToRoute('app_coord_agents', ['campagne' => $campagne->getId()]);
    }
}
