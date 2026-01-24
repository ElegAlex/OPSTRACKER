<?php

namespace App\Controller;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Repository\AgentRepository;
use App\Repository\CampagneRepository;
use App\Repository\CreneauRepository;
use App\Repository\ReservationRepository;
use App\Repository\SegmentRepository;
use App\Service\IcsGenerator;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller pour l'interface de reservation agent (acces par token).
 *
 * User Stories :
 * - US-1001 : Voir les creneaux disponibles (T-1801)
 * - US-1002 : Se positionner sur un creneau (T-1802)
 * - US-1003 : Annuler/modifier son creneau (T-1803)
 *
 * Regles metier :
 * - RG-120 : Agent ne voit que les creneaux de son segment/site
 * - RG-121 : Un agent = un seul creneau par campagne
 * - RG-122 : Confirmation automatique = email + ICS
 * - RG-123 : Verrouillage J-X
 */
#[Route('/reservation')]
class BookingController extends AbstractController
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly CampagneRepository $campagneRepository,
        private readonly CreneauRepository $creneauRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly SegmentRepository $segmentRepository,
        private readonly ReservationService $reservationService,
        private readonly IcsGenerator $icsGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * T-1801 / US-1001 : Liste des creneaux disponibles pour un agent.
     * RG-120 : Filtrage par segment/site si applicable.
     */
    #[Route('/{token}', name: 'app_booking_index', methods: ['GET'])]
    public function index(string $token): Response
    {
        $agent = $this->getAgentByToken($token);
        $campagne = $this->getCampagneActive();

        if (!$campagne) {
            return $this->render('booking/no_campagne.html.twig', [
                'agent' => $agent,
            ]);
        }

        // Verifier si l'agent a deja une reservation (RG-121)
        $reservationExistante = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);

        // T-2008 / RG-135 : Recuperer le segment de l'agent par son site
        $segment = $this->segmentRepository->findByCampagneAndSite($campagne->getId(), $agent->getSite());

        // RG-120 : Recuperer les creneaux disponibles filtres par segment
        $creneauxDisponibles = $this->creneauRepository->findDisponibles($campagne, $segment);

        // Grouper par date
        $creneauxParDate = [];
        foreach ($creneauxDisponibles as $creneau) {
            // RG-123 : Ne pas afficher les creneaux verrouilles
            if ($creneau->isVerrouillePourDate()) {
                continue;
            }

            $dateKey = $creneau->getDate()->format('Y-m-d');
            if (!isset($creneauxParDate[$dateKey])) {
                $creneauxParDate[$dateKey] = [
                    'date' => $creneau->getDate(),
                    'creneaux' => [],
                ];
            }
            $creneauxParDate[$dateKey]['creneaux'][] = $creneau;
        }
        ksort($creneauxParDate);

        return $this->render('booking/index.html.twig', [
            'agent' => $agent,
            'campagne' => $campagne,
            'token' => $token,
            'reservation' => $reservationExistante,
            'creneaux_par_date' => $creneauxParDate,
        ]);
    }

    /**
     * T-1802 / US-1002 : Selectionner un creneau.
     * RG-121 : Verifier unicite.
     * RG-122 : Confirme automatiquement.
     */
    #[Route('/{token}/choisir/{creneau}', name: 'app_booking_select', methods: ['POST'])]
    public function select(string $token, Creneau $creneau, Request $request): Response
    {
        $agent = $this->getAgentByToken($token);

        if (!$this->isCsrfTokenValid('booking_select_' . $creneau->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        try {
            // RG-121, RG-122, RG-123 : Verification et creation
            $reservation = $this->reservationService->reserver(
                $agent,
                $creneau,
                Reservation::TYPE_AGENT
            );

            $this->addFlash('success', 'Votre creneau a ete reserve avec succes.');

            return $this->redirectToRoute('app_booking_confirm', ['token' => $token]);
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }
    }

    /**
     * T-1802 / US-1002 : Page de confirmation apres reservation.
     */
    #[Route('/{token}/confirmer', name: 'app_booking_confirm', methods: ['GET'])]
    public function confirm(string $token): Response
    {
        $agent = $this->getAgentByToken($token);
        $campagne = $this->getCampagneActive();

        if (!$campagne) {
            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        $reservation = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);

        if (!$reservation) {
            $this->addFlash('warning', 'Aucune reservation trouvee.');

            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        return $this->render('booking/confirm.html.twig', [
            'agent' => $agent,
            'campagne' => $campagne,
            'token' => $token,
            'reservation' => $reservation,
        ]);
    }

    /**
     * T-1803 / US-1003 : Annuler sa reservation.
     * RG-123 : Verifier verrouillage.
     */
    #[Route('/{token}/annuler', name: 'app_booking_cancel', methods: ['POST'])]
    public function cancel(string $token, Request $request): Response
    {
        $agent = $this->getAgentByToken($token);
        $campagne = $this->getCampagneActive();

        if (!$campagne) {
            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        $reservation = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);

        if (!$reservation) {
            $this->addFlash('warning', 'Aucune reservation a annuler.');

            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        if (!$this->isCsrfTokenValid('booking_cancel', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_booking_confirm', ['token' => $token]);
        }

        // RG-123 : Verifier le verrouillage
        $creneau = $reservation->getCreneau();
        if ($creneau->isVerrouillePourDate()) {
            $this->addFlash('danger', 'Ce creneau est verrouille et ne peut plus etre annule.');

            return $this->redirectToRoute('app_booking_confirm', ['token' => $token]);
        }

        $this->reservationService->annuler($reservation);
        $this->addFlash('success', 'Votre reservation a ete annulee.');

        return $this->redirectToRoute('app_booking_index', ['token' => $token]);
    }

    /**
     * T-1803 / US-1003 : Modifier sa reservation (changer de creneau).
     * RG-123 : Verifier verrouillage.
     */
    #[Route('/{token}/modifier', name: 'app_booking_modify', methods: ['GET', 'POST'])]
    public function modify(string $token, Request $request): Response
    {
        $agent = $this->getAgentByToken($token);
        $campagne = $this->getCampagneActive();

        if (!$campagne) {
            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        $reservation = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);

        if (!$reservation) {
            $this->addFlash('warning', 'Aucune reservation a modifier.');

            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        // RG-123 : Verifier le verrouillage du creneau actuel
        $creneauActuel = $reservation->getCreneau();
        if ($creneauActuel->isVerrouillePourDate()) {
            $this->addFlash('danger', 'Ce creneau est verrouille et ne peut plus etre modifie.');

            return $this->redirectToRoute('app_booking_confirm', ['token' => $token]);
        }

        // POST : Traiter la modification
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('booking_modify', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');

                return $this->redirectToRoute('app_booking_modify', ['token' => $token]);
            }

            $nouveauCreneauId = $request->request->get('creneau');
            if (!$nouveauCreneauId) {
                $this->addFlash('danger', 'Veuillez selectionner un nouveau creneau.');

                return $this->redirectToRoute('app_booking_modify', ['token' => $token]);
            }

            $nouveauCreneau = $this->creneauRepository->find($nouveauCreneauId);
            if (!$nouveauCreneau || $nouveauCreneau->getCampagne()->getId() !== $campagne->getId()) {
                $this->addFlash('danger', 'Creneau invalide.');

                return $this->redirectToRoute('app_booking_modify', ['token' => $token]);
            }

            try {
                $this->reservationService->modifier($reservation, $nouveauCreneau);
                $this->addFlash('success', 'Votre creneau a ete modifie avec succes.');

                return $this->redirectToRoute('app_booking_confirm', ['token' => $token]);
            } catch (\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('app_booking_modify', ['token' => $token]);
            }
        }

        // GET : Afficher les creneaux disponibles
        $creneauxDisponibles = $this->creneauRepository->findDisponibles($campagne);

        // Filtrer les creneaux verrouilles et exclure le creneau actuel
        $creneauxParDate = [];
        foreach ($creneauxDisponibles as $creneau) {
            if ($creneau->isVerrouillePourDate() || $creneau->getId() === $creneauActuel->getId()) {
                continue;
            }

            $dateKey = $creneau->getDate()->format('Y-m-d');
            if (!isset($creneauxParDate[$dateKey])) {
                $creneauxParDate[$dateKey] = [
                    'date' => $creneau->getDate(),
                    'creneaux' => [],
                ];
            }
            $creneauxParDate[$dateKey]['creneaux'][] = $creneau;
        }
        ksort($creneauxParDate);

        return $this->render('booking/modify.html.twig', [
            'agent' => $agent,
            'campagne' => $campagne,
            'token' => $token,
            'reservation' => $reservation,
            'creneaux_par_date' => $creneauxParDate,
        ]);
    }

    /**
     * T-2001 / US-1004 : Page recapitulatif de la reservation.
     * Affiche tous les details et permet de telecharger l'ICS.
     */
    #[Route('/{token}/recapitulatif', name: 'app_booking_recap', methods: ['GET'])]
    public function recap(string $token): Response
    {
        $agent = $this->getAgentByToken($token);
        $campagne = $this->getCampagneActive();

        if (!$campagne) {
            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        $reservation = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);

        if (!$reservation) {
            $this->addFlash('warning', 'Aucune reservation trouvee.');

            return $this->redirectToRoute('app_booking_index', ['token' => $token]);
        }

        return $this->render('booking/recap.html.twig', [
            'agent' => $agent,
            'campagne' => $campagne,
            'token' => $token,
            'reservation' => $reservation,
        ]);
    }

    /**
     * Telecharger le fichier ICS de la reservation.
     */
    #[Route('/{token}/ics', name: 'app_booking_ics', methods: ['GET'])]
    public function downloadIcs(string $token): Response
    {
        $agent = $this->getAgentByToken($token);
        $campagne = $this->getCampagneActive();

        if (!$campagne) {
            throw $this->createNotFoundException('Aucune campagne active.');
        }

        $reservation = $this->reservationRepository->findByAgentAndCampagne($agent, $campagne);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation non trouvee.');
        }

        $icsContent = $this->icsGenerator->generate($reservation);

        $response = new Response($icsContent);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('rdv-%s-%s.ics', $campagne->getNom(), $reservation->getCreneau()->getDate()->format('Y-m-d'))
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * Recupere l'agent par son token de reservation.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function getAgentByToken(string $token): Agent
    {
        $agent = $this->agentRepository->findOneByBookingToken($token);

        if (!$agent) {
            throw $this->createNotFoundException('Lien de reservation invalide ou expire.');
        }

        if (!$agent->isActif()) {
            throw $this->createNotFoundException('Ce compte agent n\'est plus actif.');
        }

        return $agent;
    }

    /**
     * Recupere la campagne active pour les reservations.
     */
    private function getCampagneActive(): ?Campagne
    {
        // Recuperer la campagne en cours (status = "en_cours")
        $campagnes = $this->campagneRepository->findBy(['statut' => 'en_cours'], ['dateDebut' => 'DESC'], 1);

        return $campagnes[0] ?? null;
    }

    /**
     * T-2405 : Configuration opt-in SMS pour l'agent.
     * Permet a l'agent d'activer/desactiver les rappels SMS.
     */
    #[Route('/{token}/sms', name: 'app_booking_sms_optin', methods: ['GET', 'POST'])]
    public function smsOptin(string $token, Request $request): Response
    {
        $agent = $this->getAgentByToken($token);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sms_optin', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_booking_sms_optin', ['token' => $token]);
            }

            // Recuperer les donnees du formulaire
            $smsOptIn = $request->request->getBoolean('sms_optin');
            $telephone = $request->request->get('telephone');

            // Mettre a jour le telephone si fourni
            if (!empty($telephone)) {
                $agent->setTelephone($telephone);
            }

            // Mettre a jour l'opt-in
            $agent->setSmsOptIn($smsOptIn);

            $this->entityManager->flush();

            if ($smsOptIn && $agent->canReceiveSms()) {
                $this->addFlash('success', 'Vous recevrez un SMS de rappel la veille de votre rendez-vous.');
            } elseif ($smsOptIn && empty($agent->getTelephone())) {
                $this->addFlash('warning', 'Veuillez renseigner votre numero de telephone pour recevoir les SMS.');
            } else {
                $this->addFlash('info', 'Vous ne recevrez pas de rappel SMS.');
            }

            return $this->redirectToRoute('app_booking_confirm', ['token' => $token]);
        }

        return $this->render('booking/sms_optin.html.twig', [
            'agent' => $agent,
            'token' => $token,
        ]);
    }
}
