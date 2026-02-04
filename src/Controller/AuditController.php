<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Service\AuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controleur pour l'historique des modifications.
 *
 * T-1202 / US-804 : Voir l'historique des modifications (Audit)
 * RG-070 : Audit trail complet (qui, quoi, quand)
 */
#[Route('/audit')]
#[IsGranted('ROLE_GESTIONNAIRE')]
class AuditController extends AbstractController
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Historique global (admin et gestionnaires).
     */
    #[Route('', name: 'audit_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $entityType = $request->query->get('type');
        $utilisateur = $request->query->get('utilisateur');
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $dateDebutObj = $dateDebut ? new \DateTime($dateDebut) : null;
        $dateFinObj = $dateFin ? new \DateTime($dateFin . ' 23:59:59') : null;

        $historique = $this->auditService->getHistoriqueGlobal(
            $dateDebutObj,
            $dateFinObj,
            $entityType,
            $utilisateur,
            $page
        );

        return $this->render('audit/index.html.twig', [
            'historique' => $historique,
            'filtres' => [
                'type' => $entityType,
                'utilisateur' => $utilisateur,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ],
        ]);
    }

    /**
     * Historique d'une campagne.
     */
    #[Route('/campagne/{id}', name: 'audit_campagne', methods: ['GET'])]
    public function campagne(Campagne $campagne, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $campagneId = $campagne->getId();
        if (null === $campagneId) {
            throw $this->createNotFoundException('Campagne non trouvee');
        }

        $historique = $this->auditService->getHistoriqueCampagne($campagneId, $page);

        return $this->render('audit/campagne.html.twig', [
            'campagne' => $campagne,
            'historique' => $historique,
        ]);
    }

    /**
     * Historique d'une operation.
     */
    #[Route('/operation/{id}', name: 'audit_operation', methods: ['GET'])]
    public function operation(Operation $operation, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $operationId = $operation->getId();
        if (null === $operationId) {
            throw $this->createNotFoundException('Operation non trouvee');
        }

        $historique = $this->auditService->getHistorique(
            'App\Entity\Operation',
            $operationId,
            $page
        );

        return $this->render('audit/operation.html.twig', [
            'operation' => $operation,
            'historique' => $historique,
        ]);
    }
}
