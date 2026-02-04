<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\ChangePasswordType;
use App\Repository\CampagneRepository;
use App\Service\DureeInterventionService;
use App\Service\UtilisateurService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la gestion du profil utilisateur.
 *
 * T-1004 : Modifier son propre mot de passe (RG-001)
 */
#[Route('/profil')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private UtilisateurService $utilisateurService,
        private UserPasswordHasherInterface $passwordHasher,
        private DureeInterventionService $dureeService,
        private CampagneRepository $campagneRepository,
    ) {
    }

    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $stats = [];
        if ($user->isTechnicien()) {
            $stats = $this->utilisateurService->getStatistiques($user);
        }

        // Temps cumule par campagne pour ce technicien
        $tempsByCampagne = [];
        if ($user->isTechnicien()) {
            $tempsParCampagneId = $this->dureeService->getTotalParCampagnePourTechnicien($user);

            foreach ($tempsParCampagneId as $campagneId => $minutes) {
                $campagne = $this->campagneRepository->find($campagneId);
                if ($campagne && $minutes > 0) {
                    $tempsByCampagne[] = [
                        'campagne' => $campagne,
                        'minutes' => $minutes,
                        'formate' => DureeInterventionService::formatMinutes($minutes),
                    ];
                }
            }

            // Trier par temps decroissant
            usort($tempsByCampagne, fn($a, $b) => $b['minutes'] <=> $a['minutes']);
        }

        // Temps total toutes campagnes
        $tempsTotal = (int) array_sum(array_column($tempsByCampagne, 'minutes'));
        $tempsTotalFormate = DureeInterventionService::formatMinutes($tempsTotal);

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'tempsByCampagne' => $tempsByCampagne,
            'tempsTotal' => $tempsTotal,
            'tempsTotalFormate' => $tempsTotalFormate,
        ]);
    }

    /**
     * T-1004 : Modifier son propre mot de passe
     */
    #[Route('/mot-de-passe', name: 'app_profile_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if (!is_array($data)) {
                throw new \RuntimeException('Invalid form data');
            }

            $currentPassword = is_string($data['current_password']) ? $data['current_password'] : '';
            $newPassword = is_string($data['new_password']) ? $data['new_password'] : '';

            // Verifier le mot de passe actuel
            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
                return $this->render('profile/password.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            try {
                // RG-001 : Valider et mettre a jour le mot de passe
                $this->utilisateurService->updatePassword($user, $newPassword);
                $this->addFlash('success', 'Votre mot de passe a ete modifie avec succes.');
                return $this->redirectToRoute('app_profile');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('profile/password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
