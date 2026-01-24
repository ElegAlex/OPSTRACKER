<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Repository\AgentRepository;
use App\Repository\CreneauRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la vue calendrier manager.
 *
 * Sprint V2.1b - T-2301, T-2302, T-2303
 * User Story US-1014 : Vue calendrier planning
 *
 * Permet aux managers de visualiser les creneaux de leur equipe
 * sous forme de calendrier interactif avec FullCalendar.
 */
#[Route('/manager/campagne/{campagne}/calendar')]
#[IsGranted('ROLE_GESTIONNAIRE')]
class ManagerCalendarController extends AbstractController
{
    public function __construct(
        private readonly CreneauRepository $creneauRepository,
        private readonly AgentRepository $agentRepository,
    ) {
    }

    /**
     * T-2303 : Vue calendrier manager.
     * Affiche un calendrier FullCalendar avec les creneaux de la campagne.
     */
    #[Route('', name: 'app_manager_calendar', methods: ['GET'])]
    public function index(Campagne $campagne): Response
    {
        $manager = $this->getManagerAgent();

        if (!$manager) {
            $this->addFlash('danger', 'Vous n\'etes pas associe a un profil agent-manager.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        // Statistiques rapides
        $agents = $this->agentRepository->findByManager($manager);
        $totalAgents = count($agents);

        return $this->render('manager/calendar.html.twig', [
            'campagne' => $campagne,
            'manager' => $manager,
            'total_agents' => $totalAgents,
        ]);
    }

    /**
     * T-2302 : API JSON pour les evenements du calendrier.
     * Retourne les creneaux au format FullCalendar.
     *
     * Parametres GET :
     * - start : date de debut (Y-m-d)
     * - end : date de fin (Y-m-d)
     */
    #[Route('/events.json', name: 'app_manager_calendar_events', methods: ['GET'])]
    public function events(Campagne $campagne, Request $request): JsonResponse
    {
        $manager = $this->getManagerAgent();

        if (!$manager) {
            return new JsonResponse(['error' => 'Manager non trouve'], 403);
        }

        // Recuperer la plage de dates (defaut: mois en cours)
        $startParam = $request->query->get('start');
        $endParam = $request->query->get('end');

        try {
            $start = $startParam ? new \DateTime($startParam) : new \DateTime('first day of this month');
            $end = $endParam ? new \DateTime($endParam) : new \DateTime('last day of next month');
        } catch (\Exception $e) {
            $start = new \DateTime('first day of this month');
            $end = new \DateTime('last day of next month');
        }

        // Recuperer les creneaux dans la plage
        $creneaux = $this->creneauRepository->findByDateRange($campagne, $start, $end);

        // Recuperer les IDs des agents du manager
        $mesAgents = $this->agentRepository->findByManager($manager);
        $mesAgentsIds = array_map(fn ($a) => $a->getId(), $mesAgents);

        $events = [];

        foreach ($creneaux as $creneau) {
            // Filtrer les reservations confirmees
            $reservations = $creneau->getReservations()->filter(
                fn ($r) => $r->getStatut() === 'confirmee'
            );

            $placesRestantes = $creneau->getPlacesRestantes();
            $isComplet = $placesRestantes <= 0;
            $hasReservations = $reservations->count() > 0;

            // Compter les agents de mon equipe sur ce creneau
            $mesAgentsSurCreneau = [];
            foreach ($reservations as $reservation) {
                $agent = $reservation->getAgent();
                if (in_array($agent->getId(), $mesAgentsIds, true)) {
                    $mesAgentsSurCreneau[] = [
                        'id' => $agent->getId(),
                        'nom' => $agent->getNom(),
                        'prenom' => $agent->getPrenom(),
                        'reservationId' => $reservation->getId(),
                    ];
                }
            }

            // Construire le titre
            $title = $this->buildEventTitle($creneau, $reservations->count(), count($mesAgentsSurCreneau));

            // Determiner le type et les couleurs
            if ($isComplet) {
                $type = 'complet';
                $backgroundColor = '#ffebee';
                $borderColor = '#f44336';
                $textColor = '#c62828';
            } elseif ($hasReservations) {
                $type = 'reserve';
                $backgroundColor = '#e3f2fd';
                $borderColor = '#2196f3';
                $textColor = '#1565c0';
            } else {
                $type = 'disponible';
                $backgroundColor = '#e8f5e9';
                $borderColor = '#4caf50';
                $textColor = '#2e7d32';
            }

            $events[] = [
                'id' => 'creneau-' . $creneau->getId(),
                'title' => $title,
                'start' => $creneau->getDate()->format('Y-m-d') . 'T' . $creneau->getHeureDebut()->format('H:i:s'),
                'end' => $creneau->getDate()->format('Y-m-d') . 'T' . $creneau->getHeureFin()->format('H:i:s'),
                'backgroundColor' => $backgroundColor,
                'borderColor' => $borderColor,
                'textColor' => $textColor,
                'extendedProps' => [
                    'creneauId' => $creneau->getId(),
                    'capacite' => $creneau->getCapacite(),
                    'placesRestantes' => $placesRestantes,
                    'placesPrises' => $creneau->getCapacite() - $placesRestantes,
                    'lieu' => $creneau->getLieu() ?? 'Non defini',
                    'type' => $type,
                    'verrouille' => $creneau->isVerrouillePourDate(),
                    'mesAgents' => $mesAgentsSurCreneau,
                    'mesAgentsCount' => count($mesAgentsSurCreneau),
                ],
            ];
        }

        return new JsonResponse($events);
    }

    /**
     * Construit le titre de l'evenement pour le calendrier.
     */
    private function buildEventTitle($creneau, int $totalReservations, int $mesAgentsCount): string
    {
        $title = sprintf(
            '%d/%d places',
            $totalReservations,
            $creneau->getCapacite()
        );

        if ($mesAgentsCount > 0) {
            $title .= sprintf(' (%d equipe)', $mesAgentsCount);
        }

        if ($creneau->getLieu()) {
            $title .= ' - ' . $creneau->getLieu();
        }

        return $title;
    }

    /**
     * Recupere l'agent associe au manager connecte.
     */
    private function getManagerAgent(): ?\App\Entity\Agent
    {
        $user = $this->getUser();
        if (!$user) {
            return null;
        }

        return $this->agentRepository->findOneByEmail($user->getEmail());
    }
}
