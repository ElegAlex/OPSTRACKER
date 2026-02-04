<?php

namespace App\Controller;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\AgentRepository;
use App\Repository\CreneauRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour l'interface manager (positionnement agents).
 *
 * User Stories :
 * - US-1005 : Voir la liste de mes agents (T-1804)
 * - US-1006 : Positionner un agent (T-1805)
 * - US-1007 : Modifier/annuler le creneau d'un agent (T-1806)
 *
 * Regles metier :
 * - RG-121 : Un agent = un seul creneau par campagne
 * - RG-123 : Verrouillage J-X
 * - RG-124 : Manager ne voit que les agents de son service
 * - RG-125 : Tracabilite : enregistrer qui a positionne
 * - RG-126 : Notification agent si positionne par tiers
 */
#[Route('/manager/campagne/{campagne}')]
#[IsGranted('ROLE_GESTIONNAIRE')]
class ManagerBookingController extends AbstractController
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly CreneauRepository $creneauRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationService $reservationService,
    ) {
    }

    /**
     * T-1804 / US-1005 : Liste des agents du manager avec leur statut de reservation.
     * RG-124 : Filtrer par service du manager.
     */
    #[Route('/agents', name: 'app_manager_agents', methods: ['GET'])]
    public function agents(Campagne $campagne): Response
    {
        $manager = $this->getManagerAgent();

        if (!$manager) {
            $this->addFlash('danger', 'Vous n\'etes pas associe a un profil agent-manager.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        // RG-124 : Recuperer les agents du manager
        $agents = $this->agentRepository->findByManager($manager);

        // Recuperer les reservations existantes pour cette campagne
        $reservationsParAgent = [];
        foreach ($agents as $agent) {
            $reservation = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);
            $reservationsParAgent[(int) $agent->getId()] = $reservation;
        }

        // Statistiques
        $totalAgents = count($agents);
        $agentsPositionnes = count(array_filter($reservationsParAgent, fn ($r) => $r !== null));

        return $this->render('manager/agents.html.twig', [
            'campagne' => $campagne,
            'manager' => $manager,
            'agents' => $agents,
            'reservations' => $reservationsParAgent,
            'total_agents' => $totalAgents,
            'agents_positionnes' => $agentsPositionnes,
        ]);
    }

    /**
     * T-1805 / US-1006 : Positionner un agent sur un creneau.
     * RG-121, RG-125, RG-126 : Unicite, tracabilite, notification.
     */
    #[Route('/positionner/{agent}', name: 'app_manager_position', methods: ['GET', 'POST'])]
    public function position(Campagne $campagne, Agent $agent, Request $request): Response
    {
        $manager = $this->getManagerAgent();

        if (!$manager) {
            throw $this->createAccessDeniedException('Vous n\'etes pas associe a un profil agent-manager.');
        }

        // RG-124 : Verifier que l'agent appartient au manager
        if ($agent->getManager()?->getId() !== $manager->getId()) {
            throw $this->createAccessDeniedException('Cet agent n\'est pas dans votre equipe.');
        }

        // RG-121 : Verifier qu'il n'a pas deja une reservation
        $reservationExistante = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);
        if ($reservationExistante) {
            $this->addFlash('warning', sprintf(
                '%s a deja une reservation pour cette campagne.',
                $agent->getNomComplet()
            ));

            return $this->redirectToRoute('app_manager_agents', ['campagne' => $campagne->getId()]);
        }

        // POST : Traiter le positionnement
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('manager_position_' . (int) $agent->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');

                return $this->redirectToRoute('app_manager_position', [
                    'campagne' => $campagne->getId(),
                    'agent' => $agent->getId(),
                ]);
            }

            $creneauId = $request->request->get('creneau');
            if (!$creneauId) {
                $this->addFlash('danger', 'Veuillez selectionner un creneau.');

                return $this->redirectToRoute('app_manager_position', [
                    'campagne' => $campagne->getId(),
                    'agent' => $agent->getId(),
                ]);
            }

            $creneau = $this->creneauRepository->find($creneauId);
            $creneauCampagne = $creneau?->getCampagne();
            if (!$creneau || $creneauCampagne === null || $creneauCampagne->getId() !== $campagne->getId()) {
                $this->addFlash('danger', 'Creneau invalide.');

                return $this->redirectToRoute('app_manager_position', [
                    'campagne' => $campagne->getId(),
                    'agent' => $agent->getId(),
                ]);
            }

            try {
                // RG-125, RG-126 : Positionnement par manager avec tracabilite
                /** @var Utilisateur|null $currentUser */
                $currentUser = $this->getUser();
                $reservation = $this->reservationService->reserver(
                    $agent,
                    $creneau,
                    Reservation::TYPE_MANAGER,
                    $currentUser
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

                return $this->redirectToRoute('app_manager_agents', ['campagne' => $campagne->getId()]);
            } catch (\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('app_manager_position', [
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

        return $this->render('manager/position.html.twig', [
            'campagne' => $campagne,
            'agent' => $agent,
            'creneaux_par_date' => $creneauxParDate,
        ]);
    }

    /**
     * T-1806 / US-1007 : Modifier la reservation d'un agent.
     * RG-123, RG-126 : Verrouillage et notification.
     */
    #[Route('/modifier/{reservation}', name: 'app_manager_modify', methods: ['GET', 'POST'])]
    public function modify(Campagne $campagne, Reservation $reservation, Request $request): Response
    {
        $manager = $this->getManagerAgent();

        if (!$manager) {
            throw $this->createAccessDeniedException('Vous n\'etes pas associe a un profil agent-manager.');
        }

        // Verifier que la reservation concerne la bonne campagne
        $reservationCampagne = $reservation->getCampagne();
        if ($reservationCampagne === null || $reservationCampagne->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Reservation non trouvee dans cette campagne.');
        }

        $agent = $reservation->getAgent();
        if ($agent === null) {
            throw $this->createNotFoundException('Agent non trouve pour cette reservation.');
        }

        // RG-124 : Verifier que l'agent appartient au manager
        if ($agent->getManager()?->getId() !== $manager->getId()) {
            throw $this->createAccessDeniedException('Cet agent n\'est pas dans votre equipe.');
        }

        // RG-123 : Verifier le verrouillage
        $creneauActuel = $reservation->getCreneau();
        if ($creneauActuel === null) {
            throw $this->createNotFoundException('Creneau de la reservation non trouve.');
        }
        if ($creneauActuel->isVerrouillePourDate()) {
            $this->addFlash('danger', 'Ce creneau est verrouille et ne peut plus etre modifie.');

            return $this->redirectToRoute('app_manager_agents', ['campagne' => $campagne->getId()]);
        }

        // POST : Traiter la modification
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('manager_modify_' . (int) $reservation->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');

                return $this->redirectToRoute('app_manager_modify', [
                    'campagne' => $campagne->getId(),
                    'reservation' => $reservation->getId(),
                ]);
            }

            $nouveauCreneauId = $request->request->get('creneau');
            if (!$nouveauCreneauId) {
                $this->addFlash('danger', 'Veuillez selectionner un nouveau creneau.');

                return $this->redirectToRoute('app_manager_modify', [
                    'campagne' => $campagne->getId(),
                    'reservation' => $reservation->getId(),
                ]);
            }

            $nouveauCreneau = $this->creneauRepository->find($nouveauCreneauId);
            $nouveauCreneauCampagne = $nouveauCreneau?->getCampagne();
            if (!$nouveauCreneau || $nouveauCreneauCampagne === null || $nouveauCreneauCampagne->getId() !== $campagne->getId()) {
                $this->addFlash('danger', 'Creneau invalide.');

                return $this->redirectToRoute('app_manager_modify', [
                    'campagne' => $campagne->getId(),
                    'reservation' => $reservation->getId(),
                ]);
            }

            try {
                // RG-126 : La modification declenche une notification
                $this->reservationService->modifier($reservation, $nouveauCreneau);

                $this->addFlash('success', sprintf(
                    'Le creneau de %s a ete modifie.',
                    $agent->getNomComplet()
                ));

                return $this->redirectToRoute('app_manager_agents', ['campagne' => $campagne->getId()]);
            } catch (\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('app_manager_modify', [
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

        return $this->render('manager/modify.html.twig', [
            'campagne' => $campagne,
            'agent' => $agent,
            'reservation' => $reservation,
            'creneaux_par_date' => $creneauxParDate,
        ]);
    }

    /**
     * T-1806 / US-1007 : Annuler la reservation d'un agent.
     * RG-123, RG-126 : Verrouillage et notification.
     */
    #[Route('/annuler/{reservation}', name: 'app_manager_cancel', methods: ['POST'])]
    public function cancel(Campagne $campagne, Reservation $reservation, Request $request): Response
    {
        $manager = $this->getManagerAgent();

        if (!$manager) {
            throw $this->createAccessDeniedException('Vous n\'etes pas associe a un profil agent-manager.');
        }

        // Verifier que la reservation concerne la bonne campagne
        $reservationCampagne = $reservation->getCampagne();
        if ($reservationCampagne === null || $reservationCampagne->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Reservation non trouvee dans cette campagne.');
        }

        $agent = $reservation->getAgent();
        if ($agent === null) {
            throw $this->createNotFoundException('Agent non trouve pour cette reservation.');
        }

        // RG-124 : Verifier que l'agent appartient au manager
        if ($agent->getManager()?->getId() !== $manager->getId()) {
            throw $this->createAccessDeniedException('Cet agent n\'est pas dans votre equipe.');
        }

        if (!$this->isCsrfTokenValid('manager_cancel_' . (int) $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_manager_agents', ['campagne' => $campagne->getId()]);
        }

        // RG-123 : Verifier le verrouillage
        $creneau = $reservation->getCreneau();
        if ($creneau === null) {
            throw $this->createNotFoundException('Creneau de la reservation non trouve.');
        }
        if ($creneau->isVerrouillePourDate()) {
            $this->addFlash('danger', 'Ce creneau est verrouille et ne peut plus etre annule.');

            return $this->redirectToRoute('app_manager_agents', ['campagne' => $campagne->getId()]);
        }

        // RG-126 : Annuler declenche une notification
        $this->reservationService->annuler($reservation);

        $this->addFlash('success', sprintf(
            'La reservation de %s a ete annulee.',
            $agent->getNomComplet()
        ));

        return $this->redirectToRoute('app_manager_agents', ['campagne' => $campagne->getId()]);
    }

    /**
     * T-2002 / US-1008 : Vue planning manager.
     * RG-127 : Alerte si >50% de l'equipe positionnee le meme jour.
     */
    #[Route('/planning', name: 'app_manager_planning', methods: ['GET'])]
    public function planning(Campagne $campagne): Response
    {
        $manager = $this->getManagerAgent();

        if (!$manager) {
            $this->addFlash('danger', 'Vous n\'etes pas associe a un profil agent-manager.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        // Recuperer le total des agents de l'equipe
        $agents = $this->agentRepository->findByManager($manager);
        $totalAgents = count($agents);

        // RG-127 : Recuperer les agents positionnes par date
        $agentsParDate = $this->reservationRepository->findAgentsByDateForManager($manager, $campagne);

        // Calculer les taux et detecter les alertes
        $planningData = [];
        foreach ($agentsParDate as $dateKey => $agentsJour) {
            $nbAgents = count($agentsJour);
            $taux = $totalAgents > 0 ? round(($nbAgents / $totalAgents) * 100) : 0;

            $planningData[$dateKey] = [
                'date' => new \DateTime($dateKey),
                'agents' => $agentsJour,
                'count' => $nbAgents,
                'taux' => $taux,
                'alerte' => $taux > 50, // RG-127
            ];
        }

        // Trier par date
        ksort($planningData);

        // Statistiques globales
        $totalPositionnes = count(array_unique(array_merge(...array_values($agentsParDate) ?: [[]])));
        $joursAvecAlerte = count(array_filter($planningData, fn ($d) => $d['alerte']));

        return $this->render('manager/planning.html.twig', [
            'campagne' => $campagne,
            'manager' => $manager,
            'total_agents' => $totalAgents,
            'total_positionnes' => $totalPositionnes,
            'planning' => $planningData,
            'jours_avec_alerte' => $joursAvecAlerte,
        ]);
    }

    /**
     * Recupere l'agent associe au manager connecte.
     * Le manager est un utilisateur IT qui est aussi un Agent.
     */
    private function getManagerAgent(): ?Agent
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return null;
        }

        // On cherche l'agent par email de l'utilisateur connecte
        $email = $user->getEmail();
        if ($email === null) {
            return null;
        }
        return $this->agentRepository->findOneByEmail($email);
    }
}
