<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\HabilitationCampagne;
use App\Entity\Utilisateur;
use App\Repository\HabilitationCampagneRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controleur pour la gestion des habilitations par campagne.
 *
 * T-1205 / US-808 : Gerer les habilitations par campagne
 * RG-115 : Droits granulaires (voir, positionner, configurer, exporter)
 */
#[Route('/campagnes/{id}/habilitations')]
class HabilitationController extends AbstractController
{
    public function __construct(
        private readonly HabilitationCampagneRepository $habilitationRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'app_campagne_habilitations', methods: ['GET'])]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function index(Campagne $campagne): Response
    {
        // Verifier que l'utilisateur peut configurer cette campagne
        $this->denyAccessUnlessGranted('CAMPAGNE_CONFIGURER', $campagne);

        $habilitations = $this->habilitationRepository->findByCampagne($campagne);
        $utilisateursDisponibles = $this->getUtilisateursDisponibles($campagne);

        return $this->render('habilitation/index.html.twig', [
            'campagne' => $campagne,
            'habilitations' => $habilitations,
            'utilisateurs' => $utilisateursDisponibles,
            'droits' => HabilitationCampagne::DROITS,
        ]);
    }

    #[Route('/ajouter', name: 'app_campagne_habilitation_add', methods: ['POST'])]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function add(Request $request, Campagne $campagne): Response
    {
        $this->denyAccessUnlessGranted('CAMPAGNE_CONFIGURER', $campagne);

        $utilisateurId = $request->request->getInt('utilisateur_id');
        $utilisateur = $this->utilisateurRepository->find($utilisateurId);

        if (!$utilisateur) {
            $this->addFlash('danger', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_campagne_habilitations', ['id' => $campagne->getId()]);
        }

        // Verifier si une habilitation existe deja
        $existing = $this->habilitationRepository->findByCampagneAndUtilisateur($campagne, $utilisateur);
        if ($existing) {
            $this->addFlash('warning', sprintf('%s a déjà une habilitation sur cette campagne.', $utilisateur->getNomComplet()));
            return $this->redirectToRoute('app_campagne_habilitations', ['id' => $campagne->getId()]);
        }

        // Creer l'habilitation avec les droits
        $habilitation = new HabilitationCampagne();
        $habilitation->setCampagne($campagne);
        $habilitation->setUtilisateur($utilisateur);
        $habilitation->setPeutVoir($request->request->has('droit_voir'));
        $habilitation->setPeutPositionner($request->request->has('droit_positionner'));
        $habilitation->setPeutConfigurer($request->request->has('droit_configurer'));
        $habilitation->setPeutExporter($request->request->has('droit_exporter'));

        $this->em->persist($habilitation);
        $this->em->flush();

        $this->addFlash('success', sprintf(
            'Habilitation ajoutée pour %s : %s',
            $utilisateur->getNomComplet(),
            $habilitation->getDroitsLabel()
        ));

        return $this->redirectToRoute('app_campagne_habilitations', ['id' => $campagne->getId()]);
    }

    #[Route('/{habilitationId}/modifier', name: 'app_campagne_habilitation_edit', methods: ['POST'])]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function edit(Request $request, Campagne $campagne, int $habilitationId): Response
    {
        $this->denyAccessUnlessGranted('CAMPAGNE_CONFIGURER', $campagne);

        $habilitation = $this->habilitationRepository->find($habilitationId);

        if (!$habilitation || $habilitation->getCampagne() !== $campagne) {
            $this->addFlash('danger', 'Habilitation non trouvée.');
            return $this->redirectToRoute('app_campagne_habilitations', ['id' => $campagne->getId()]);
        }

        $habilitation->setPeutVoir($request->request->has('droit_voir'));
        $habilitation->setPeutPositionner($request->request->has('droit_positionner'));
        $habilitation->setPeutConfigurer($request->request->has('droit_configurer'));
        $habilitation->setPeutExporter($request->request->has('droit_exporter'));

        $this->em->flush();

        $utilisateur = $habilitation->getUtilisateur();
        if (!$utilisateur) {
            throw new \RuntimeException('Utilisateur non trouvé pour cette habilitation.');
        }

        $this->addFlash('success', sprintf(
            'Habilitation mise à jour pour %s.',
            $utilisateur->getNomComplet()
        ));

        return $this->redirectToRoute('app_campagne_habilitations', ['id' => $campagne->getId()]);
    }

    #[Route('/{habilitationId}/supprimer', name: 'app_campagne_habilitation_delete', methods: ['POST'])]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function delete(Request $request, Campagne $campagne, int $habilitationId): Response
    {
        $this->denyAccessUnlessGranted('CAMPAGNE_CONFIGURER', $campagne);

        $habilitation = $this->habilitationRepository->find($habilitationId);

        if (!$habilitation || $habilitation->getCampagne() !== $campagne) {
            $this->addFlash('danger', 'Habilitation non trouvée.');
            return $this->redirectToRoute('app_campagne_habilitations', ['id' => $campagne->getId()]);
        }

        // Verification CSRF
        if (!$this->isCsrfTokenValid('delete_habilitation_' . $habilitationId, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_campagne_habilitations', ['id' => $campagne->getId()]);
        }

        $utilisateur = $habilitation->getUtilisateur();
        if (!$utilisateur) {
            throw new \RuntimeException('Utilisateur non trouvé pour cette habilitation.');
        }

        $nomUtilisateur = $utilisateur->getNomComplet();
        $this->em->remove($habilitation);
        $this->em->flush();

        $this->addFlash('success', sprintf('Habilitation supprimée pour %s.', $nomUtilisateur));

        return $this->redirectToRoute('app_campagne_habilitations', ['id' => $campagne->getId()]);
    }

    /**
     * Retourne les utilisateurs qui n'ont pas encore d'habilitation sur cette campagne.
     *
     * @return Utilisateur[]
     */
    private function getUtilisateursDisponibles(Campagne $campagne): array
    {
        $habilitationsExistantes = $this->habilitationRepository->findByCampagne($campagne);
        $utilisateursHabilites = array_map(
            function (HabilitationCampagne $h): ?int {
                $utilisateur = $h->getUtilisateur();
                return $utilisateur?->getId();
            },
            $habilitationsExistantes
        );
        $utilisateursHabilites = array_filter($utilisateursHabilites, fn ($id) => $id !== null);

        // Ajouter le proprietaire
        $proprietaire = $campagne->getProprietaire();
        if ($proprietaire) {
            $utilisateursHabilites[] = $proprietaire->getId();
        }

        $tous = $this->utilisateurRepository->findBy(['actif' => true], ['nom' => 'ASC', 'prenom' => 'ASC']);

        return array_filter($tous, fn(Utilisateur $u) => !in_array($u->getId(), $utilisateursHabilites, true));
    }
}
