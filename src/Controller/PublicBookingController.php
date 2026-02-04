<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\ReservationEndUser;
use App\Repository\AgentRepository;
use App\Repository\CampagneAgentAutoriseRepository;
use App\Repository\CampagneRepository;
use App\Repository\OperationRepository;
use App\Repository\ReservationEndUserRepository;
use App\Service\PersonnesAutoriseesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller pour la reservation publique d'operations (type Doodle).
 *
 * Acces par token campagne. L'utilisateur s'identifie via dropdown ou saisie libre.
 * Supporte les reservations multiples si operation.capacite > 1.
 */
#[Route('/reservation/c')]
class PublicBookingController extends AbstractController
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly CampagneAgentAutoriseRepository $campagneAgentAutoriseRepository,
        private readonly CampagneRepository $campagneRepository,
        private readonly OperationRepository $operationRepository,
        private readonly ReservationEndUserRepository $reservationEndUserRepository,
        private readonly PersonnesAutoriseesService $personnesAutoriseesService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Page d'accueil : affiche les operations disponibles a la reservation.
     */
    #[Route('/{token}', name: 'app_public_booking_index', methods: ['GET'])]
    public function index(string $token, Request $request): Response
    {
        $campagne = $this->getCampagneByToken($token);

        if (!$campagne) {
            return $this->render('booking/public/no_campagne.html.twig', [
                'message' => 'Cette page de reservation n\'est pas disponible.',
            ]);
        }

        // Verifier si deja identifie via session
        $session = $request->getSession();
        $identifiant = $session->get('booking_identifiant_' . $campagne->getId());

        if (!$identifiant) {
            return $this->redirectToRoute('app_public_booking_identify', ['token' => $token]);
        }

        // Verifier si l'utilisateur a deja reserve une operation
        $reservationsUtilisateur = $this->reservationEndUserRepository->findByIdentifiantAndCampagne($identifiant, $campagne);

        if (!empty($reservationsUtilisateur)) {
            // Prendre la premiere reservation pour afficher la confirmation
            $operationReservee = $reservationsUtilisateur[0]->getOperation();

            return $this->render('booking/public/already_booked.html.twig', [
                'campagne' => $campagne,
                'operation' => $operationReservee,
                'identifiant' => $identifiant,
                'token' => $token,
                'reservations' => $reservationsUtilisateur,
            ]);
        }

        // Afficher les operations avec des places disponibles
        $operations = $this->operationRepository->findAvecPlacesDisponiblesByCampagne($campagne);

        // Grouper par date si datePlanifiee existe
        $operationsParDate = [];
        $operationsSansDate = [];

        foreach ($operations as $operation) {
            $date = $operation->getDatePlanifiee();
            if ($date) {
                $dateKey = $date->format('Y-m-d');
                if (!isset($operationsParDate[$dateKey])) {
                    $operationsParDate[$dateKey] = [
                        'date' => $date,
                        'operations' => [],
                    ];
                }
                $operationsParDate[$dateKey]['operations'][] = $operation;
            } else {
                $operationsSansDate[] = $operation;
            }
        }
        ksort($operationsParDate);

        return $this->render('booking/public/index.html.twig', [
            'campagne' => $campagne,
            'operations_par_date' => $operationsParDate,
            'operations_sans_date' => $operationsSansDate,
            'identifiant' => $identifiant,
            'token' => $token,
        ]);
    }

    /**
     * Page d'identification : dropdown ou saisie libre selon mode campagne.
     */
    #[Route('/{token}/identification', name: 'app_public_booking_identify', methods: ['GET', 'POST'])]
    public function identify(string $token, Request $request): Response
    {
        $campagne = $this->getCampagneByToken($token);

        if (!$campagne) {
            return $this->render('booking/public/no_campagne.html.twig', [
                'message' => 'Cette page de reservation n\'est pas disponible.',
            ]);
        }

        $mode = $campagne->getReservationMode() ?? Campagne::RESERVATION_MODE_LIBRE;

        // Recuperer les personnes autorisees selon le mode
        $personnes = $this->personnesAutoriseesService->getPersonnesAutorisees($campagne);

        if ($request->isMethod('POST')) {
            /** @var string|null $csrfToken */
            $csrfToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('public_booking_identify', $csrfToken)) {
                $this->addFlash('danger', 'Token CSRF invalide.');

                return $this->redirectToRoute('app_public_booking_identify', ['token' => $token]);
            }

            $identifiant = trim((string) $request->request->get('identifiant', ''));

            if (empty($identifiant)) {
                $this->addFlash('danger', 'Veuillez renseigner votre identifiant.');

                return $this->redirectToRoute('app_public_booking_identify', ['token' => $token]);
            }

            // Valider l'identifiant selon le mode
            $valide = false;

            if ($mode === Campagne::RESERVATION_MODE_LIBRE) {
                // Mode libre : accepter tout identifiant non vide
                $valide = true;
            } elseif ($mode === Campagne::RESERVATION_MODE_IMPORT) {
                // Mode import : verifier que l'identifiant est dans la liste autorisee
                $agentAutorise = $this->campagneAgentAutoriseRepository->findOneByIdentifiant($campagne, $identifiant);
                $valide = ($agentAutorise !== null);
            } elseif ($mode === Campagne::RESERVATION_MODE_ANNUAIRE) {
                // Mode annuaire : verifier que l'identifiant est dans la liste filtree
                foreach ($personnes as $personne) {
                    if ($personne['id'] === $identifiant) {
                        $valide = true;
                        break;
                    }
                }
            }

            if ($valide) {
                // Stocker l'identifiant en session
                $request->getSession()->set('booking_identifiant_' . $campagne->getId(), $identifiant);

                return $this->redirectToRoute('app_public_booking_index', ['token' => $token]);
            }

            $this->addFlash('danger', 'Identification invalide. Verifiez votre saisie.');
        }

        // Champ d'identification pour le mode libre (defaut: "nom ou identifiant")
        $champIdentification = $campagne->getReservationChampIdentification() ?: 'nom ou identifiant';

        return $this->render('booking/public/identify.html.twig', [
            'campagne' => $campagne,
            'mode' => $mode,
            'personnes' => $personnes,
            'token' => $token,
            'champIdentification' => $champIdentification,
        ]);
    }

    /**
     * Reservation d'une operation.
     */
    #[Route('/{token}/reserver/{operation}', name: 'app_public_booking_select', methods: ['POST'])]
    public function select(string $token, Operation $operation, Request $request): Response
    {
        $campagne = $this->getCampagneByToken($token);

        $operationCampagne = $operation->getCampagne();
        if (!$campagne || $operationCampagne === null || $operationCampagne->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation invalide.');
        }

        $session = $request->getSession();
        $identifiant = $session->get('booking_identifiant_' . $campagne->getId());

        if (!$identifiant) {
            return $this->redirectToRoute('app_public_booking_identify', ['token' => $token]);
        }

        /** @var string|null $csrfToken */
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('public_booking_select_' . $operation->getId(), $csrfToken)) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_public_booking_index', ['token' => $token]);
        }

        // Verifier que l'operation a des places disponibles
        if ($operation->isComplet()) {
            $this->addFlash('danger', 'Ce creneau est complet.');

            return $this->redirectToRoute('app_public_booking_index', ['token' => $token]);
        }

        // Verifier que l'utilisateur n'a pas deja reserve ce creneau specifique
        $dejaReserveCeCreneau = $this->reservationEndUserRepository->findByIdentifiantAndOperation($identifiant, $operation);

        if ($dejaReserveCeCreneau) {
            $this->addFlash('danger', 'Vous avez deja reserve ce creneau.');

            return $this->redirectToRoute('app_public_booking_index', ['token' => $token]);
        }

        // Verifier que l'utilisateur n'a pas deja reserve un autre creneau dans cette campagne
        $reservationsUtilisateur = $this->reservationEndUserRepository->findByIdentifiantAndCampagne($identifiant, $campagne);

        if (!empty($reservationsUtilisateur)) {
            $this->addFlash('danger', 'Vous avez deja reserve un creneau pour cette campagne.');

            return $this->redirectToRoute('app_public_booking_index', ['token' => $token]);
        }

        // Recuperer les infos completes de l'agent depuis la table Agent
        $agent = $this->agentRepository->findOneBy(['matricule' => $identifiant])
              ?? $this->agentRepository->findOneBy(['email' => $identifiant]);

        if ($agent) {
            // Agent trouve dans l'annuaire
            $nomPrenom = trim($agent->getNom() . ' ' . $agent->getPrenom());
            $service = $agent->getService();
            $site = $agent->getSite();
            $email = $agent->getEmail();
        } else {
            // Essayer via le service (mode import avec CampagneAgentAutorise)
            $personneInfos = $this->personnesAutoriseesService->getPersonneInfos($campagne, $identifiant);

            $nomPrenom = $personneInfos['nomPrenom'] ?? $identifiant;
            $service = $personneInfos['service'] ?? null;
            $site = $personneInfos['site'] ?? null;
            $email = $personneInfos['email'] ?? null;
        }

        // Creer la reservation end-user
        $reservation = new ReservationEndUser();
        $reservation->setOperation($operation);
        $reservation->setIdentifiant($identifiant);
        $reservation->setNomPrenom($nomPrenom);
        $reservation->setService($service);
        $reservation->setSite($site);
        $reservation->setEmail($email);

        $operation->addReservationEndUser($reservation);
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre creneau a ete reserve avec succes.');

        return $this->redirectToRoute('app_public_booking_confirm', [
            'token' => $token,
            'operation' => $operation->getId(),
        ]);
    }

    /**
     * Page de confirmation apres reservation.
     */
    #[Route('/{token}/confirmation/{operation}', name: 'app_public_booking_confirm', methods: ['GET'])]
    public function confirm(string $token, Operation $operation): Response
    {
        $campagne = $this->getCampagneByToken($token);

        $operationCampagne = $operation->getCampagne();
        if (!$campagne || $operationCampagne === null || $operationCampagne->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Operation non trouvee.');
        }

        return $this->render('booking/public/confirm.html.twig', [
            'campagne' => $campagne,
            'operation' => $operation,
            'identifiant' => $operation->getReservePar(),
            'token' => $token,
        ]);
    }

    /**
     * Annuler sa reservation.
     */
    #[Route('/{token}/annuler', name: 'app_public_booking_cancel', methods: ['POST'])]
    public function cancel(string $token, Request $request): Response
    {
        $campagne = $this->getCampagneByToken($token);

        if (!$campagne) {
            throw $this->createNotFoundException('Campagne non trouvee.');
        }

        $session = $request->getSession();
        $identifiant = $session->get('booking_identifiant_' . $campagne->getId());

        if (!$identifiant) {
            return $this->redirectToRoute('app_public_booking_identify', ['token' => $token]);
        }

        // Trouver les reservations de cet utilisateur pour cette campagne
        $reservations = $this->reservationEndUserRepository->findByIdentifiantAndCampagne($identifiant, $campagne);

        if (empty($reservations)) {
            $this->addFlash('warning', 'Aucune reservation a annuler.');

            return $this->redirectToRoute('app_public_booking_index', ['token' => $token]);
        }

        /** @var string|null $csrfToken */
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('public_booking_cancel', $csrfToken)) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_public_booking_index', ['token' => $token]);
        }

        // Annuler toutes les reservations de cet utilisateur
        foreach ($reservations as $reservation) {
            $this->entityManager->remove($reservation);
        }
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre reservation a ete annulee.');

        return $this->redirectToRoute('app_public_booking_index', ['token' => $token]);
    }

    /**
     * Deconnexion / changer d'identite.
     */
    #[Route('/{token}/deconnexion', name: 'app_public_booking_logout', methods: ['POST'])]
    public function logout(string $token, Request $request): Response
    {
        $campagne = $this->getCampagneByToken($token);

        if ($campagne) {
            $request->getSession()->remove('booking_identifiant_' . $campagne->getId());
        }

        return $this->redirectToRoute('app_public_booking_identify', ['token' => $token]);
    }

    /**
     * Recupere la campagne par son token de partage.
     * Verifie que la reservation est ouverte.
     */
    private function getCampagneByToken(string $token): ?Campagne
    {
        $campagne = $this->campagneRepository->findOneBy(['shareToken' => $token]);

        if (!$campagne || !$campagne->isReservationOuverte()) {
            return null;
        }

        // Verifier que la campagne n'est pas archivee
        if ($campagne->isReadOnly()) {
            return null;
        }

        return $campagne;
    }
}
