<?php

namespace App\Controller;

use App\Repository\OperationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la recherche globale (T-907 / US-308).
 */
#[Route('/recherche')]
#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    public function __construct(
        private readonly OperationRepository $operationRepository,
    ) {
    }

    /**
     * Page de recherche globale.
     */
    #[Route('', name: 'app_search', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $results = [];
        $totalCount = 0;

        if (!empty($query) && strlen($query) >= 2) {
            $results = $this->operationRepository->searchGlobal($query, 100);
            $totalCount = $this->operationRepository->countSearchGlobal($query);
        }

        // Grouper les resultats par campagne
        $groupedResults = [];
        foreach ($results as $operation) {
            $campagne = $operation->getCampagne();
            if (null === $campagne) {
                continue;
            }
            $campagneId = $campagne->getId();
            if (!isset($groupedResults[$campagneId])) {
                $groupedResults[$campagneId] = [
                    'campagne' => $campagne,
                    'operations' => [],
                ];
            }
            $groupedResults[$campagneId]['operations'][] = $operation;
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'grouped_results' => $groupedResults,
            'total_count' => $totalCount,
        ]);
    }

    /**
     * API de recherche pour l'autocompletion.
     */
    #[Route('/api', name: 'app_search_api', methods: ['GET'])]
    public function api(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => [], 'count' => 0]);
        }

        $results = $this->operationRepository->searchGlobal($query, 10);

        $data = [];
        foreach ($results as $operation) {
            $campagne = $operation->getCampagne();
            $data[] = [
                'id' => $operation->getId(),
                'matricule' => $operation->getDisplayIdentifier(),
                'nom' => $operation->getDisplayName(),
                'statut' => $operation->getStatutLabel(),
                'statut_couleur' => $operation->getStatutCouleur(),
                'campagne' => $campagne?->getNom(),
                'campagne_id' => $campagne?->getId(),
            ];
        }

        return new JsonResponse([
            'results' => $data,
            'count' => count($data),
            'total' => $this->operationRepository->countSearchGlobal($query),
        ]);
    }
}
