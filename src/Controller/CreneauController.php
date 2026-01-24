<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Form\CreneauGenerationType;
use App\Form\CreneauType;
use App\Repository\CreneauRepository;
use App\Service\CreneauService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la gestion des creneaux d'une campagne.
 *
 * User Stories :
 * - US-1101 : Creer des creneaux (T-1701, T-1702, T-1703)
 * - US-1104 : Modifier un creneau (T-1704)
 * - US-1105 : Supprimer un creneau (T-1705)
 * - US-1106 : Voir le taux de remplissage (T-1706)
 *
 * Regles metier :
 * - RG-130 : Creation creneaux manuelle ou generation automatique
 * - RG-133 : Modification creneau = notification agents si reservations
 * - RG-134 : Suppression creneau = confirmation si reservations + notification
 */
#[Route('/campagnes/{campagne}/creneaux')]
#[IsGranted('ROLE_USER')]
class CreneauController extends AbstractController
{
    public function __construct(
        private readonly CreneauService $creneauService,
        private readonly CreneauRepository $creneauRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * T-1701 / US-1101 : Liste des creneaux avec taux de remplissage.
     * T-1706 / US-1106 : Widget taux de remplissage.
     */
    #[Route('', name: 'app_creneau_index', methods: ['GET'])]
    public function index(Campagne $campagne): Response
    {
        $creneaux = $this->creneauRepository->findByCampagne($campagne);

        // Grouper les creneaux par date
        $creneauxParDate = [];
        foreach ($creneaux as $creneau) {
            $dateKey = $creneau->getDate()->format('Y-m-d');
            if (!isset($creneauxParDate[$dateKey])) {
                $creneauxParDate[$dateKey] = [
                    'date' => $creneau->getDate(),
                    'creneaux' => [],
                ];
            }
            $creneauxParDate[$dateKey]['creneaux'][] = $creneau;
        }

        // Trier par date
        ksort($creneauxParDate);

        // Statistiques globales
        $statistiques = $this->creneauRepository->getStatistiquesRemplissage($campagne);

        return $this->render('creneau/index.html.twig', [
            'campagne' => $campagne,
            'creneaux_par_date' => $creneauxParDate,
            'statistiques' => $statistiques,
        ]);
    }

    /**
     * T-1702 / US-1101 : Creer un creneau manuellement.
     * RG-130 : Creation manuelle.
     */
    #[Route('/nouveau', name: 'app_creneau_new', methods: ['GET', 'POST'])]
    public function new(Campagne $campagne, Request $request): Response
    {
        $creneau = new Creneau();
        $creneau->setCampagne($campagne);

        $form = $this->createForm(CreneauType::class, $creneau, [
            'campagne' => $campagne,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($creneau);
            $this->entityManager->flush();

            $this->addFlash('success', 'Creneau cree avec succes.');

            return $this->redirectToRoute('app_creneau_index', ['campagne' => $campagne->getId()]);
        }

        return $this->render('creneau/new.html.twig', [
            'campagne' => $campagne,
            'creneau' => $creneau,
            'form' => $form,
        ]);
    }

    /**
     * T-1703 / US-1101 : Generer des creneaux automatiquement.
     * RG-130 : Generation automatique (skip weekends, pause dejeuner).
     */
    #[Route('/generer', name: 'app_creneau_generate', methods: ['GET', 'POST'])]
    public function generate(Campagne $campagne, Request $request): Response
    {
        $form = $this->createForm(CreneauGenerationType::class, null, [
            'campagne' => $campagne,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Preparer les plages horaires (eviter pause dejeuner 12h-14h)
            $heureDebut = $data['heure_debut'];
            $heureFin = $data['heure_fin'];

            $plagesHoraires = [];

            // Si la plage chevauche 12h-14h, on la coupe
            $debutMinutes = (int) $heureDebut->format('H') * 60 + (int) $heureDebut->format('i');
            $finMinutes = (int) $heureFin->format('H') * 60 + (int) $heureFin->format('i');

            if ($debutMinutes < 12 * 60 && $finMinutes > 14 * 60) {
                // Plage du matin (jusqu'a 12h)
                $plagesHoraires[] = [
                    'debut' => $heureDebut->format('H:i'),
                    'fin' => '12:00',
                ];
                // Plage de l'apres-midi (a partir de 14h)
                $plagesHoraires[] = [
                    'debut' => '14:00',
                    'fin' => $heureFin->format('H:i'),
                ];
            } elseif ($finMinutes <= 12 * 60) {
                // Uniquement le matin
                $plagesHoraires[] = [
                    'debut' => $heureDebut->format('H:i'),
                    'fin' => $heureFin->format('H:i'),
                ];
            } elseif ($debutMinutes >= 14 * 60) {
                // Uniquement l'apres-midi
                $plagesHoraires[] = [
                    'debut' => $heureDebut->format('H:i'),
                    'fin' => $heureFin->format('H:i'),
                ];
            } elseif ($debutMinutes < 12 * 60 && $finMinutes <= 14 * 60) {
                // Matin uniquement (fin avant ou a 14h)
                $plagesHoraires[] = [
                    'debut' => $heureDebut->format('H:i'),
                    'fin' => min($heureFin->format('H:i'), '12:00'),
                ];
            } elseif ($debutMinutes >= 12 * 60 && $debutMinutes < 14 * 60) {
                // Debut pendant la pause, on commence a 14h
                $plagesHoraires[] = [
                    'debut' => '14:00',
                    'fin' => $heureFin->format('H:i'),
                ];
            } else {
                $plagesHoraires[] = [
                    'debut' => $heureDebut->format('H:i'),
                    'fin' => $heureFin->format('H:i'),
                ];
            }

            $creneaux = $this->creneauService->genererPlage(
                $campagne,
                $data['date_debut'],
                $data['date_fin'],
                $data['duree_minutes'],
                $data['capacite'],
                $data['lieu'],
                $data['segment'],
                $plagesHoraires
            );

            $this->addFlash('success', sprintf('%d creneaux generes avec succes.', count($creneaux)));

            return $this->redirectToRoute('app_creneau_index', ['campagne' => $campagne->getId()]);
        }

        return $this->render('creneau/generate.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
        ]);
    }

    /**
     * T-1704 / US-1104 : Modifier un creneau.
     * RG-133 : Notification agents si reservations existantes.
     */
    #[Route('/{id}/modifier', name: 'app_creneau_edit', methods: ['GET', 'POST'])]
    public function edit(Campagne $campagne, Creneau $creneau, Request $request): Response
    {
        // Verifier que le creneau appartient a la campagne
        if ($creneau->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Creneau non trouve dans cette campagne.');
        }

        // Sauvegarder les valeurs initiales pour detecter les changements
        $dateInitiale = $creneau->getDate();
        $heureDebutInitiale = $creneau->getHeureDebut();
        $heureFinInitiale = $creneau->getHeureFin();

        $hasReservations = $this->creneauService->hasReservations($creneau);

        $form = $this->createForm(CreneauType::class, $creneau, [
            'campagne' => $campagne,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // RG-133 : Verifier si date/heure a change et s'il y a des reservations
            $dateChanged = $dateInitiale != $creneau->getDate();
            $heureChanged = $heureDebutInitiale != $creneau->getHeureDebut()
                || $heureFinInitiale != $creneau->getHeureFin();

            $this->entityManager->flush();

            // Envoyer notifications si modifications temporelles et reservations
            if ($hasReservations && ($dateChanged || $heureChanged)) {
                $this->notifierAgentsModification($creneau);
                $this->addFlash('info', 'Les agents ayant reserve ont ete notifies de la modification.');
            }

            $this->addFlash('success', 'Creneau modifie avec succes.');

            return $this->redirectToRoute('app_creneau_index', ['campagne' => $campagne->getId()]);
        }

        return $this->render('creneau/edit.html.twig', [
            'campagne' => $campagne,
            'creneau' => $creneau,
            'form' => $form,
            'has_reservations' => $hasReservations,
        ]);
    }

    /**
     * T-1705 / US-1105 : Supprimer un creneau.
     * RG-134 : Confirmation si reservations + notification.
     */
    #[Route('/{id}/supprimer', name: 'app_creneau_delete', methods: ['POST'])]
    public function delete(Campagne $campagne, Creneau $creneau, Request $request): Response
    {
        // Verifier que le creneau appartient a la campagne
        if ($creneau->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Creneau non trouve dans cette campagne.');
        }

        if (!$this->isCsrfTokenValid('delete_creneau_' . $creneau->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_creneau_index', ['campagne' => $campagne->getId()]);
        }

        // RG-134 : Si reservations, les annuler et notifier
        $hasReservations = $this->creneauService->hasReservations($creneau);
        if ($hasReservations) {
            $this->annulerReservationsEtNotifier($creneau);
        }

        // Supprimer le creneau
        $this->creneauService->supprimer($creneau);

        if ($hasReservations) {
            $this->addFlash('warning', 'Creneau supprime. Les agents ont ete notifies de l\'annulation de leur reservation.');
        } else {
            $this->addFlash('success', 'Creneau supprime avec succes.');
        }

        return $this->redirectToRoute('app_creneau_index', ['campagne' => $campagne->getId()]);
    }

    /**
     * RG-133 : Notifier les agents d'une modification de creneau.
     */
    private function notifierAgentsModification(Creneau $creneau): void
    {
        foreach ($creneau->getReservations() as $reservation) {
            if ($reservation->isConfirmee()) {
                try {
                    $this->notificationService->envoyerModification($reservation, $creneau);
                } catch (\Exception $e) {
                    // Log error but continue
                }
            }
        }
    }

    /**
     * RG-134 : Annuler les reservations et notifier les agents.
     */
    private function annulerReservationsEtNotifier(Creneau $creneau): void
    {
        foreach ($creneau->getReservations() as $reservation) {
            if ($reservation->isConfirmee()) {
                $reservation->annuler();
                try {
                    $this->notificationService->envoyerAnnulation($reservation);
                } catch (\Exception $e) {
                    // Log error but continue
                }
            }
        }
        $this->entityManager->flush();
    }
}
