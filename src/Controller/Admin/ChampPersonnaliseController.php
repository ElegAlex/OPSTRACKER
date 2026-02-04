<?php

namespace App\Controller\Admin;

use App\Entity\TypeOperation;
use App\Service\ChampPersonnaliseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controleur pour la gestion des champs personnalises (RG-061).
 *
 * T-1201 / US-802 : Definir les champs personnalises
 */
#[Route('/admin/type-operation')]
#[IsGranted('ROLE_ADMIN')]
class ChampPersonnaliseController extends AbstractController
{
    public function __construct(
        private readonly ChampPersonnaliseService $champService,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('/{id}/champs', name: 'admin_type_operation_champs', methods: ['GET', 'POST'])]
    public function edit(Request $request, TypeOperation $typeOperation): Response
    {
        $champs = $typeOperation->getChampsPersonnalises() ?? [];
        $erreurs = [];
        $success = false;

        if ($request->isMethod('POST')) {
            /** @var array<int|string, array<string, mixed>> $champsData */
            $champsData = $request->request->all('champs');
            $nouveauxChamps = [];

            foreach ($champsData as $index => $champData) {
                // S'assurer que $champData est un array
                if (!is_array($champData)) {
                    continue;
                }

                // Ignorer les champs vides
                if (empty($champData['code']) && empty($champData['label'])) {
                    continue;
                }

                $champ = [
                    'code' => trim($champData['code'] ?? ''),
                    'label' => trim($champData['label'] ?? ''),
                    'type' => $champData['type'] ?? ChampPersonnaliseService::TYPE_TEXTE_COURT,
                    'obligatoire' => isset($champData['obligatoire']),
                    'options' => [],
                ];

                // Traiter les options pour les listes
                if ($champ['type'] === ChampPersonnaliseService::TYPE_LISTE) {
                    $optionsStr = trim($champData['options'] ?? '');
                    $champ['options'] = $this->champService->parseOptions($optionsStr);
                }

                $nouveauxChamps[] = $champ;
            }

            // Valider
            $typeOperation->setChampsPersonnalises($nouveauxChamps);
            $erreurs = $this->champService->validerChampsTypeOperation($typeOperation);

            if (empty($erreurs)) {
                $this->em->flush();
                $this->addFlash('success', sprintf(
                    'Les %d champs personnalisés ont été enregistrés pour le type "%s".',
                    count($nouveauxChamps),
                    $typeOperation->getNom()
                ));
                $success = true;
                $champs = $nouveauxChamps;
            }
        }

        return $this->render('admin/type_operation/champs.html.twig', [
            'typeOperation' => $typeOperation,
            'champs' => $champs,
            'types' => ChampPersonnaliseService::TYPES,
            'erreurs' => $erreurs,
            'success' => $success,
            'champService' => $this->champService,
        ]);
    }
}
