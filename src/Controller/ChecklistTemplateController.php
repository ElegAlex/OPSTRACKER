<?php

namespace App\Controller;

use App\Entity\ChecklistTemplate;
use App\Repository\ChecklistTemplateRepository;
use App\Service\ChecklistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la gestion des templates de checklist.
 *
 * User Stories :
 * - US-504 : Modifier un template avec versioning (T-1104)
 * - US-505 : Creer des phases dans un template (T-1105)
 */
#[Route('/templates')]
#[IsGranted('ROLE_GESTIONNAIRE')]
class ChecklistTemplateController extends AbstractController
{
    public function __construct(
        private readonly ChecklistService $checklistService,
        private readonly ChecklistTemplateRepository $templateRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Liste des templates de checklist.
     */
    #[Route('', name: 'app_template_index', methods: ['GET'])]
    public function index(): Response
    {
        $templates = $this->templateRepository->findBy([], ['nom' => 'ASC']);

        return $this->render('template/index.html.twig', [
            'templates' => $templates,
        ]);
    }

    /**
     * Detail d'un template.
     */
    #[Route('/{id}', name: 'app_template_show', methods: ['GET'])]
    public function show(ChecklistTemplate $template): Response
    {
        $instancesCount = $template->getInstances()->count();
        $campagnesCount = $template->getCampagnes()->count();

        return $this->render('template/show.html.twig', [
            'template' => $template,
            'instances_count' => $instancesCount,
            'campagnes_count' => $campagnesCount,
        ]);
    }

    /**
     * T-1104 / US-504 : Modifier un template avec versioning.
     * RG-031 : Modification = nouvelle version, instances existantes conservent leur structure
     */
    #[Route('/{id}/modifier', name: 'app_template_edit', methods: ['GET', 'POST'])]
    public function edit(ChecklistTemplate $template, Request $request): Response
    {
        $instancesCount = $template->getInstances()->count();
        $willCreateNewVersion = $instancesCount > 0;

        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $description = $request->request->get('description');
            $phasesData = $request->request->all('phases');

            // Validation
            if (!is_string($nom) || $nom === '') {
                $this->addFlash('danger', 'Le nom du template est obligatoire.');
                return $this->redirectToRoute('app_template_edit', ['id' => $template->getId()]);
            }

            // RG-031 : Si des instances existent, incrementer la version
            if ($willCreateNewVersion) {
                $template->incrementVersion();
            }

            // Mettre a jour les donnees
            $template->setNom($nom);
            $template->setDescription(is_string($description) ? $description : null);

            // Reconstruire la structure des phases
            $etapes = ['phases' => []];
            foreach ($phasesData as $index => $phaseData) {
                if (!is_array($phaseData)) {
                    continue;
                }
                $phase = [
                    'id' => 'phase-' . ($index + 1),
                    'nom' => $phaseData['nom'] ?? 'Phase ' . ($index + 1),
                    'ordre' => $index + 1,
                    'verrouillable' => isset($phaseData['verrouillable']),
                    'etapes' => [],
                ];

                if (isset($phaseData['etapes']) && is_array($phaseData['etapes'])) {
                    foreach ($phaseData['etapes'] as $etapeIndex => $etapeData) {
                        if (!empty($etapeData['titre'])) {
                            $phase['etapes'][] = [
                                'id' => $phase['id'] . '-etape-' . ($etapeIndex + 1),
                                'titre' => $etapeData['titre'],
                                'description' => $etapeData['description'] ?? null,
                                'ordre' => $etapeIndex + 1,
                                'obligatoire' => isset($etapeData['obligatoire']),
                                'documentId' => !empty($etapeData['documentId']) ? (int)$etapeData['documentId'] : null,
                            ];
                        }
                    }
                }

                $etapes['phases'][] = $phase;
            }

            $template->setEtapes($etapes);
            $this->entityManager->flush();

            if ($willCreateNewVersion) {
                $this->addFlash('success', sprintf(
                    'Template modifie. Nouvelle version v%d creee. Les %d instances existantes conservent la structure precedente.',
                    $template->getVersion(),
                    $instancesCount
                ));
            } else {
                $this->addFlash('success', 'Template modifie avec succes.');
            }

            return $this->redirectToRoute('app_template_show', ['id' => $template->getId()]);
        }

        return $this->render('template/edit.html.twig', [
            'template' => $template,
            'instances_count' => $instancesCount,
            'will_create_new_version' => $willCreateNewVersion,
        ]);
    }

    /**
     * Creer un nouveau template.
     */
    #[Route('/nouveau', name: 'app_template_new', methods: ['GET', 'POST'], priority: 10)]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $description = $request->request->get('description');

            if (!is_string($nom) || $nom === '') {
                $this->addFlash('danger', 'Le nom du template est obligatoire.');
                return $this->redirectToRoute('app_template_new');
            }

            $template = new ChecklistTemplate();
            $template->setNom($nom);
            $template->setDescription(is_string($description) ? $description : null);
            $template->setVersion(1);
            $template->setActif(true);

            // Creer une phase par defaut
            $template->setEtapes([
                'phases' => [
                    [
                        'id' => 'phase-1',
                        'nom' => 'Phase 1',
                        'ordre' => 1,
                        'verrouillable' => false,
                        'etapes' => [],
                    ],
                ],
            ]);

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            $this->addFlash('success', 'Template cree avec succes. Vous pouvez maintenant ajouter des phases et des etapes.');

            return $this->redirectToRoute('app_template_edit', ['id' => $template->getId()]);
        }

        return $this->render('template/new.html.twig');
    }

    /**
     * Toggle actif/inactif.
     */
    #[Route('/{id}/toggle', name: 'app_template_toggle', methods: ['POST'])]
    public function toggle(ChecklistTemplate $template, Request $request): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('template_toggle_' . $template->getId(), is_string($token) ? $token : null)) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_template_index');
        }

        $this->checklistService->toggleTemplateActif($template);

        $this->addFlash('success', sprintf(
            'Template "%s" %s.',
            $template->getNom(),
            $template->isActif() ? 'active' : 'desactive'
        ));

        return $this->redirectToRoute('app_template_index');
    }
}
